<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankStatementLine;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        app(\App\Services\Accounting\ChartOfAccounts::class)->refresh();
    }

    /** Posts a balanced Dr Bank / Cr Revenue entry on a given date. */
    private function postBankReceipt(string $date, float $amount): JournalEntry
    {
        $bank = $this->account('1020');
        $revenue = $this->account('4010');

        $entry = JournalEntry::create([
            'entry_date' => $date,
            'narration' => 'test bank receipt',
            'status' => JournalEntry::STATUS_DRAFT,
            'currency' => 'KWD',
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $bank->id,
            'debit' => $amount, 'credit' => 0,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => 0, 'credit' => $amount,
        ]);

        return $entry->refresh()->post();
    }

    public function test_recompute_book_balances_matches_gl(): void
    {
        $bank = $this->account('1020');

        $this->postBankReceipt('2026-05-05', 100.000);
        $this->postBankReceipt('2026-05-10', 200.000);
        $this->postBankReceipt('2026-05-25', 50.000);

        $rec = BankReconciliation::create([
            'account_id' => $bank->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'opening_balance' => 0,
            'closing_balance' => 350.000,
            'status' => 'in_progress',
        ]);
        $rec->recomputeBookBalances();
        $rec->save();

        $this->assertEqualsWithDelta(0.0, $rec->book_opening_balance, 0.001);
        $this->assertEqualsWithDelta(350.0, $rec->book_closing_balance, 0.001);
    }

    public function test_recon_code_is_auto_generated(): void
    {
        $bank = $this->account('1020');

        $rec = BankReconciliation::create([
            'account_id' => $bank->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'opening_balance' => 0,
            'closing_balance' => 0,
            'status' => 'in_progress',
        ]);

        $this->assertNotEmpty($rec->code);
        $this->assertStringStartsWith('BR-', $rec->code);
    }

    public function test_match_pairs_statement_line_with_je_line(): void
    {
        $bank = $this->account('1020');
        $je = $this->postBankReceipt('2026-05-10', 100.000);
        $bankJeLine = $je->lines->where('debit', '>', 0)->first(); // The bank-side line

        $rec = BankReconciliation::create([
            'account_id' => $bank->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'opening_balance' => 0,
            'closing_balance' => 100.000,
            'status' => 'in_progress',
        ]);

        $bsl = BankStatementLine::create([
            'bank_reconciliation_id' => $rec->id,
            'statement_date' => '2026-05-10',
            'description' => 'Patient deposit',
            'debit' => 100.000,
            'credit' => 0,
        ]);

        $this->assertFalse($bsl->isMatched());

        $bsl->match($bankJeLine->id);

        $bsl->refresh();
        $this->assertTrue($bsl->isMatched());
        $this->assertEquals($bankJeLine->id, $bsl->matched_journal_entry_line_id);
        $this->assertNotNull($bsl->matched_at);
    }

    public function test_unmatch_removes_link(): void
    {
        $bank = $this->account('1020');
        $je = $this->postBankReceipt('2026-05-10', 75.000);
        $line = $je->lines->where('debit', '>', 0)->first();

        $rec = BankReconciliation::create([
            'account_id' => $bank->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'opening_balance' => 0,
            'closing_balance' => 75,
            'status' => 'in_progress',
        ]);
        $bsl = BankStatementLine::create([
            'bank_reconciliation_id' => $rec->id,
            'statement_date' => '2026-05-10',
            'debit' => 75.000, 'credit' => 0,
        ]);

        $bsl->match($line->id);
        $this->assertTrue($bsl->refresh()->isMatched());

        $bsl->unmatch();

        $bsl->refresh();
        $this->assertFalse($bsl->isMatched());
        $this->assertNull($bsl->matched_journal_entry_line_id);
        $this->assertNull($bsl->matched_at);
    }

    public function test_match_rejects_amount_mismatch(): void
    {
        $bank = $this->account('1020');
        $je = $this->postBankReceipt('2026-05-10', 100.000);
        $line = $je->lines->where('debit', '>', 0)->first();

        $rec = BankReconciliation::create([
            'account_id' => $bank->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'opening_balance' => 0,
            'closing_balance' => 50,
            'status' => 'in_progress',
        ]);
        $bsl = BankStatementLine::create([
            'bank_reconciliation_id' => $rec->id,
            'statement_date' => '2026-05-10',
            'debit' => 50.000,  // != 100 on the JE line
            'credit' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $bsl->match($line->id);
    }

    /**
     * Regression: a single GL line cannot back two bank statement lines
     * (audit follow-up R3). Without this guard, the reconciliation would
     * silently double-count the same cash movement.
     *
     * Two enforcement layers are tested:
     *   1. App-level check in BankStatementLine::match() — throws RuntimeException
     *   2. DB-level unique index from 2026_05_20_145852 — catches direct writes
     */
    public function test_same_gl_line_cannot_match_two_bank_lines(): void
    {
        $bank = $this->account('1020');
        $je = $this->postBankReceipt('2026-05-10', 100.000);
        $bankJeLine = $je->lines->where('debit', '>', 0)->first();

        $rec = BankReconciliation::create([
            'account_id' => $bank->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'opening_balance' => 0,
            'closing_balance' => 100.000,
            'status' => 'in_progress',
        ]);

        // First bank line matches OK.
        $first = BankStatementLine::create([
            'bank_reconciliation_id' => $rec->id,
            'statement_date' => '2026-05-10',
            'description' => 'First',
            'debit' => 100.000, 'credit' => 0,
        ]);
        $first->match($bankJeLine->id);
        $this->assertTrue($first->refresh()->isMatched());

        // Second bank line tries to match the SAME GL line.
        $second = BankStatementLine::create([
            'bank_reconciliation_id' => $rec->id,
            'statement_date' => '2026-05-10',
            'description' => 'Second (illegal)',
            'debit' => 100.000, 'credit' => 0,
        ]);

        // App-level rejection.
        try {
            $second->match($bankJeLine->id);
            $this->fail('match() should have rejected an already-matched GL line');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already matched', $e->getMessage());
        }

        // DB-level rejection: bypass the app guard and assert the unique
        // index does its job too.
        try {
            $second->forceFill([
                'matched_journal_entry_line_id' => $bankJeLine->id,
                'matched_at' => now(),
            ])->save();
            $this->fail('Unique index should have blocked a duplicate matched_journal_entry_line_id');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0] ?? null);
        }

        // First line is still the only one pointing at the GL line.
        $this->assertSame(
            1,
            BankStatementLine::query()
                ->where('matched_journal_entry_line_id', $bankJeLine->id)
                ->count()
        );
    }
}
