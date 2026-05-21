<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\ClinicItem;
use App\Models\ClinicStockMovement;
use App\Models\DoctorCompensationLedger;
use App\Models\VisitPayment;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected AccountingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(AccountingService::class);
        app(ChartOfAccounts::class)->refresh();
    }

    // ============================================================
    // recordVisitPayment
    // ============================================================

    public function test_paid_visit_payment_posts_balanced_entry(): void
    {
        $visit = $this->makeVisit();
        $payment = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 25.000,
            'method' => 'cash',
            'status' => 'paid',
            'kind' => 'consultation',
            'paid_at' => now(),
        ]);

        // The observer should have already auto-posted, but call directly to assert.
        $entry = $this->svc->recordVisitPayment($payment);

        $this->assertNotNull($entry);
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertEqualsWithDelta(25.0, $entry->totalDebit(), 0.001);
        $this->assertEqualsWithDelta(25.0, $entry->totalCredit(), 0.001);
        $this->assertBooksBalance();
    }

    public function test_pending_visit_payment_does_not_post(): void
    {
        $visit = $this->makeVisit();
        $payment = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 25.000,
            'method' => 'cash',
            'status' => 'pending',
            'kind' => 'consultation',
        ]);

        $entry = $this->svc->recordVisitPayment($payment);

        $this->assertNull($entry);
    }

    public function test_record_visit_payment_is_idempotent(): void
    {
        $visit = $this->makeVisit();
        $payment = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 25.000, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);

        $first = $this->svc->recordVisitPayment($payment);
        $second = $this->svc->recordVisitPayment($payment);
        $third = $this->svc->recordVisitPayment($payment);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, JournalEntry::query()
            ->where('source_type', VisitPayment::class)
            ->where('source_id', $payment->id)
            ->count());
    }

    public function test_refunded_payment_reverses_original_entry(): void
    {
        $visit = $this->makeVisit();
        $payment = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 25.000, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);

        // Find the original entry created by the observer (already fired on create).
        $original = JournalEntry::query()
            ->where('source_type', VisitPayment::class)
            ->where('source_id', $payment->id)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->first();
        $this->assertNotNull($original);

        // Flip to refunded — the observer-on-update reverses the original.
        $payment->update(['status' => 'refunded']);

        $original->refresh();
        $this->assertSame(JournalEntry::STATUS_REVERSED, $original->status);
        $this->assertNotNull($original->reversed_by_id);

        // Idempotency: a manual call afterwards must not double-reverse.
        $extra = $this->svc->recordVisitPayment($payment);
        $this->assertNull($extra, 'Second refund call must be a no-op');

        // Net effect on the GL: zero, books still balanced.
        $this->assertBooksBalance();
        $this->assertEqualsWithDelta(0.0, $this->account('1010')->balanceAt(now()->toDateString()), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->account('4010')->balanceAt(now()->toDateString()), 0.001);
    }

    public function test_zero_amount_payment_does_not_post(): void
    {
        $visit = $this->makeVisit();
        $payment = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 0,
            'method' => 'cash',
            'status' => 'paid',
            'kind' => 'consultation',
            'paid_at' => now(),
        ]);

        $entry = $this->svc->recordVisitPayment($payment);

        $this->assertNull($entry);
    }

    public function test_payment_method_routes_to_correct_account(): void
    {
        $visit = $this->makeVisit();

        // Cash → 1010
        $cashPay = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 10.000, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);
        $cashEntry = $this->svc->recordVisitPayment($cashPay);

        // KNET → 1020 (bank)
        $knetPay = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 15.000, 'method' => 'knet', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);
        $knetEntry = $this->svc->recordVisitPayment($knetPay);

        $cashAccountId = $cashEntry->lines->where('debit', '>', 0)->first()->account_id;
        $knetAccountId = $knetEntry->lines->where('debit', '>', 0)->first()->account_id;

        // Cash hits one of the cash accounts (1010 parent or 1010-X branch sub-account)
        $this->assertNotEquals($cashAccountId, $knetAccountId, 'Cash and KNET must route to different accounts');
        $this->assertContains(\App\Models\Accounting\Account::find($knetAccountId)->code, ['1020', '1020-'.$visit->branch_id]);
    }

    public function test_kind_routes_to_correct_revenue_account(): void
    {
        $visit = $this->makeVisit();

        $payConsult = VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 10, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation', 'paid_at' => now(),
        ]);
        $paySvc = VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 20, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'services', 'paid_at' => now(),
        ]);

        $consultEntry = $this->svc->recordVisitPayment($payConsult);
        $svcEntry = $this->svc->recordVisitPayment($paySvc);

        $consultRevAccount = $consultEntry->lines->where('credit', '>', 0)->first()->account;
        $svcRevAccount = $svcEntry->lines->where('credit', '>', 0)->first()->account;

        $this->assertSame('4010', $consultRevAccount->code, 'Consultation revenue should hit 4010');
        $this->assertSame('4020', $svcRevAccount->code, 'Services revenue should hit 4020');
    }

    // ============================================================
    // recordStockConsume / recordStockRestock
    // ============================================================

    public function test_stock_consume_posts_cogs_credit_inventory(): void
    {
        $f = $this->seedClinicFixtures();
        $item = ClinicItem::create([
            'name' => ['en' => 'Test Drug', 'ar' => 'دواء'],
            'default_cost' => 2.000,
            'default_price' => 5.000,
            'is_stockable' => true,
            'is_billable' => true,
            'is_active' => true,
            'type' => 'consumable',
        ]);
        $movement = ClinicStockMovement::create([
            'branch_id' => $f['branch']->id,
            'clinic_item_id' => $item->id,
            'type' => 'consume',
            'qty_change_base' => -5,
            'before_qty_base' => 100,
            'after_qty_base' => 95,
        ]);

        $entry = $this->svc->recordStockConsume($movement);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(10.0, $entry->totalDebit(), 0.001); // 5 * 2.0
        $this->assertEqualsWithDelta(10.0, $entry->totalCredit(), 0.001);
        $this->assertBooksBalance();

        // Inventory (1200) credit-decreased; COGS (5010) debit-increased.
        $cogs = $entry->lines->where('debit', '>', 0)->first();
        $inv = $entry->lines->where('credit', '>', 0)->first();
        $this->assertSame('5010', $cogs->account->code);
        $this->assertSame('1200', $inv->account->code);
    }

    public function test_zero_cost_item_does_not_post_cogs(): void
    {
        $f = $this->seedClinicFixtures();
        $item = ClinicItem::create([
            'name' => ['en' => 'Free Service', 'ar' => 'خدمة'],
            'default_cost' => 0,
            'default_price' => 10.000,
            'is_stockable' => false,
            'is_billable' => true,
            'is_active' => true,
            'type' => 'service',
        ]);
        $movement = ClinicStockMovement::create([
            'branch_id' => $f['branch']->id,
            'clinic_item_id' => $item->id,
            'type' => 'consume',
            'qty_change_base' => -1,
            'before_qty_base' => 0,
            'after_qty_base' => 0,
        ]);

        $entry = $this->svc->recordStockConsume($movement);

        $this->assertNull($entry);
    }

    public function test_restock_posts_inventory_dr_cash_cr(): void
    {
        $f = $this->seedClinicFixtures();
        $item = ClinicItem::create([
            'name' => ['en' => 'Test Drug', 'ar' => 'دواء'],
            'default_cost' => 3.000,
            'default_price' => 8.000,
            'is_stockable' => true,
            'is_billable' => true,
            'is_active' => true,
            'type' => 'consumable',
        ]);
        $movement = ClinicStockMovement::create([
            'branch_id' => $f['branch']->id,
            'clinic_item_id' => $item->id,
            'type' => 'restock',
            'qty_change_base' => 10,
            'before_qty_base' => 0,
            'after_qty_base' => 10,
        ]);

        $entry = $this->svc->recordStockRestock($movement);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(30.0, $entry->totalDebit(), 0.001);
        $this->assertEqualsWithDelta(30.0, $entry->totalCredit(), 0.001);

        $inventory = $entry->lines->where('debit', '>', 0)->first();
        $this->assertSame('1200', $inventory->account->code);
    }

    // ============================================================
    // recordDoctorCompensation
    // ============================================================

    public function test_doctor_compensation_posts_expense_and_payable(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit();
        $ledger = DoctorCompensationLedger::create([
            'visit_id' => $visit->id,
            'doctor_id' => $f['doctor']->id,
            'branch_id' => $f['branch']->id,
            'doctor_cut_amount' => 7.500,
            'fees_snapshot' => 25,
        ]);

        $entry = $this->svc->recordDoctorCompensation($ledger);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(7.5, $entry->totalDebit(), 0.001);

        $exp = $entry->lines->where('debit', '>', 0)->first();
        $pay = $entry->lines->where('credit', '>', 0)->first();
        $this->assertSame('6010', $exp->account->code);
        $this->assertSame('2020', $pay->account->code);
        $this->assertBooksBalance();
    }

    public function test_zero_cut_does_not_post(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit();
        $ledger = DoctorCompensationLedger::create([
            'visit_id' => $visit->id,
            'doctor_id' => $f['doctor']->id,
            'branch_id' => $f['branch']->id,
            'doctor_cut_amount' => 0,
            'fees_snapshot' => 25,
        ]);

        $entry = $this->svc->recordDoctorCompensation($ledger);

        $this->assertNull($entry);
    }

    /**
     * Regression for the reverse → re-accrue cycle.
     *
     * After posting once, changing the cut, and calling the service again,
     * the original entry must be reversed AND a NEW entry posted with the
     * corrected amount. The previous bug was that the reversal kept the
     * source link, so the (source_type, source_id, status='posted') unique
     * index blocked the re-post and postBalancedEntry()'s 23000-catch
     * silently returned the reversal as if it were the corrected entry.
     */
    public function test_doctor_cut_change_reverses_old_entry_and_posts_new(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit();
        $ledger = DoctorCompensationLedger::create([
            'visit_id' => $visit->id,
            'doctor_id' => $f['doctor']->id,
            'branch_id' => $f['branch']->id,
            'doctor_cut_amount' => 7.500,
            'fees_snapshot' => 25,
        ]);

        $first = $this->svc->recordDoctorCompensation($ledger);
        $this->assertNotNull($first);
        $this->assertEqualsWithDelta(7.5, $first->totalDebit(), 0.001);

        // Cut gets recomputed (e.g. doctor compensation policy changed).
        $ledger->forceFill(['doctor_cut_amount' => 12.000])->save();

        $second = $this->svc->recordDoctorCompensation($ledger);

        // The new posting must reflect the corrected amount and be a
        // DIFFERENT journal entry from the first.
        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id, 'Re-accrual must create a new JE, not return the reversal');
        $this->assertEqualsWithDelta(12.0, $second->totalDebit(), 0.001);
        $this->assertSame(JournalEntry::STATUS_POSTED, $second->refresh()->status);

        // The original was reversed.
        $first->refresh();
        $this->assertSame(JournalEntry::STATUS_REVERSED, $first->status);
        $this->assertNotNull($first->reversed_by_id);

        // The reversal entry must NOT carry the source link — that is what
        // breaks the re-accrue path if it ever regresses.
        $reversal = JournalEntry::find($first->reversed_by_id);
        $this->assertNotNull($reversal);
        $this->assertNull($reversal->source_type, 'Reversal must not carry source_type');
        $this->assertNull($reversal->source_id, 'Reversal must not carry source_id');
        // Inverse traceability via the new FK column.
        $this->assertSame($first->id, $reversal->reversal_of_id);
        $this->assertSame($first->id, $reversal->reversalOf->id);

        // existingFor() must point at the new (12.0) entry, not the reversal.
        $live = JournalEntry::query()
            ->where('source_type', DoctorCompensationLedger::class)
            ->where('source_id', $ledger->id)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->latest('id')
            ->first();
        $this->assertSame($second->id, $live->id);

        // Net GL effect: payable should be 12.0 (original 7.5 + reversal -7.5 + new 12.0).
        $this->assertBooksBalance();
        $this->assertEqualsWithDelta(
            12.0,
            $this->account('2020')->balanceAt(now()->toDateString()),
            0.001,
            'Doctor Payable should reflect ONLY the corrected accrual'
        );
    }

    /**
     * Regression: the unique index on (source_type, source_id, status) MUST
     * block a duplicate "posted" row for the same source — this is the
     * race-protection backstop that lets postBalancedEntry() catch SQLSTATE
     * 23000 and dedupe parallel posts. We can't easily simulate two real
     * concurrent threads in a unit test, but we can pretend by writing the
     * conflicting row directly and asserting the DB rejects it.
     */
    public function test_unique_index_blocks_duplicate_posted_entry_per_source(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit();
        $payment = \App\Models\VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 25.000,
            'method' => 'cash',
            'status' => 'paid',
            'kind' => 'consultation',
            'paid_at' => now(),
        ]);

        // Observer-fired post lives in the DB after create.
        $original = JournalEntry::query()
            ->where('source_type', \App\Models\VisitPayment::class)
            ->where('source_id', $payment->id)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->first();
        $this->assertNotNull($original);

        // Forge a second "posted" entry pointing at the same source. The
        // unique index from 2026_05_20_145734 must reject the write.
        try {
            JournalEntry::create([
                'entry_date' => now()->toDateString(),
                'narration' => 'Conflicting parallel post',
                'status' => JournalEntry::STATUS_POSTED, // already at posted; bypass post() entirely
                'source_type' => \App\Models\VisitPayment::class,
                'source_id' => $payment->id,
                'currency' => 'KWD',
                'posted_at' => now(),
                'code' => 'JE-CONFLICT-1',
            ]);
            $this->fail('Unique (source_type, source_id, status) index should have blocked a duplicate POSTED row');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0] ?? null);
        }

        // Original is still the single live entry for this source.
        $this->assertSame(
            1,
            JournalEntry::query()
                ->where('source_type', \App\Models\VisitPayment::class)
                ->where('source_id', $payment->id)
                ->where('status', JournalEntry::STATUS_POSTED)
                ->count()
        );
    }

    /**
     * Regression backstop for the model-level guard on journal_entry_lines.
     * One JE line cannot carry BOTH a positive debit and a positive credit
     * (that's two postings, not one), AND debit/credit must be non-negative.
     * The DB CHECK constraints (MySQL 8.0.16+) are a second layer, but the
     * model guard is the one tested here because it runs on every driver.
     */
    public function test_journal_entry_line_rejects_both_sides_and_negatives(): void
    {
        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'narration' => 'bad-line probe',
            'status' => JournalEntry::STATUS_DRAFT,
            'currency' => 'KWD',
        ]);
        $accountId = $this->account('1010')->id;

        // Both sides > 0 — rejected.
        try {
            \App\Models\Accounting\JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit' => 5, 'credit' => 5,
            ]);
            $this->fail('JE line with both debit and credit > 0 must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cannot carry both', $e->getMessage());
        }

        // Negative debit — rejected.
        try {
            \App\Models\Accounting\JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit' => -1, 'credit' => 0,
            ]);
            $this->fail('JE line with negative debit must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('non-negative', $e->getMessage());
        }

        // Both zero — rejected.
        try {
            \App\Models\Accounting\JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit' => 0, 'credit' => 0,
            ]);
            $this->fail('JE line with both sides zero must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('at least one', $e->getMessage());
        }
    }
}
