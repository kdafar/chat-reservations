<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class PeriodCloseServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected PeriodCloseService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->svc = app(PeriodCloseService::class);
    }

    /** Post a quick balanced entry on a date within $periodCode. */
    private function postEntry(string $entryDate, int $debitAccountId, int $creditAccountId, float $amount, string $narration = 'test'): JournalEntry
    {
        $entry = JournalEntry::create([
            'entry_date' => $entryDate,
            'narration' => $narration,
            'status' => JournalEntry::STATUS_DRAFT,
            'currency' => 'KWD',
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $debitAccountId,
            'debit' => $amount, 'credit' => 0,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $creditAccountId,
            'debit' => 0, 'credit' => $amount,
        ]);

        return $entry->refresh()->post();
    }

    public function test_close_zeros_all_pnl_accounts(): void
    {
        $period = AccountingPeriod::forDate('2026-04-15');

        // Post some April activity: revenue + expense
        $cash = $this->account('1110');
        $revenue = $this->account('4110');
        $expense = $this->account('6210'); // Rent

        $this->postEntry('2026-04-10', $cash->id, $revenue->id, 1000.000, 'April revenue');
        $this->postEntry('2026-04-15', $expense->id, $cash->id, 300.000, 'April rent');

        // Pre-close: revenue has 1000, expense has 300
        $this->assertEqualsWithDelta(1000.0, $revenue->balanceAt('2026-04-30'), 0.001);
        $this->assertEqualsWithDelta(300.0, $expense->balanceAt('2026-04-30'), 0.001);

        $closingEntry = $this->svc->close($period);

        $this->assertNotNull($closingEntry);
        $this->assertTrue($closingEntry->isBalanced());

        // P&L accounts should all be zero at end of April
        $this->assertEqualsWithDelta(0.0, $revenue->balanceAt('2026-04-30'), 0.001);
        $this->assertEqualsWithDelta(0.0, $expense->balanceAt('2026-04-30'), 0.001);

        // Retained Earnings should carry net profit (1000 - 300 = 700)
        $re = $this->account('3400');
        $this->assertEqualsWithDelta(700.0, $re->balanceAt('2026-04-30'), 0.001);

        // Period is closed
        $period->refresh();
        $this->assertSame(AccountingPeriod::STATUS_CLOSED, $period->status);
        $this->assertBooksBalance();
    }

    public function test_close_refuses_when_drafts_exist(): void
    {
        $period = AccountingPeriod::forDate('2026-04-15');

        $cash = $this->account('1110');
        $revenue = $this->account('4110');

        // Post one entry, leave one as draft
        $this->postEntry('2026-04-10', $cash->id, $revenue->id, 100.000);

        $draft = JournalEntry::create([
            'entry_date' => '2026-04-20',
            'narration' => 'draft',
            'status' => JournalEntry::STATUS_DRAFT,
            'currency' => 'KWD',
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $draft->id,
            'account_id' => $cash->id, 'debit' => 50, 'credit' => 0,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $draft->id,
            'account_id' => $revenue->id, 'debit' => 0, 'credit' => 50,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('draft entries');

        $this->svc->close($period);
    }

    public function test_close_refuses_if_already_closed(): void
    {
        $period = AccountingPeriod::forDate('2026-04-15');
        $period->forceFill(['status' => AccountingPeriod::STATUS_CLOSED, 'closed_at' => now()])->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already closed');

        $this->svc->close($period);
    }

    public function test_reopen_reverses_closing_entry(): void
    {
        $period = AccountingPeriod::forDate('2026-04-15');

        $cash = $this->account('1110');
        $revenue = $this->account('4110');

        $this->postEntry('2026-04-10', $cash->id, $revenue->id, 500.000);
        $closingEntry = $this->svc->close($period);

        $this->assertEqualsWithDelta(0.0, $revenue->balanceAt('2026-04-30'), 0.001);

        $this->svc->reopen($period);

        // Period back to open
        $this->assertFalse($period->refresh()->isClosed());

        // Closing JE has been reversed
        $closingEntry->refresh();
        $this->assertSame(JournalEntry::STATUS_REVERSED, $closingEntry->status);
        $this->assertNotNull($closingEntry->reversed_by_id);

        // Revenue is back to its pre-close balance as of *today* — the reversal
        // entry is dated now() to preserve the historical audit trail at the
        // closing date. So the trial balance at end-of-period still shows the
        // close (audit-preserved), but current view reflects revenue restored.
        $this->assertEqualsWithDelta(500.0, $revenue->balanceAt(now()->toDateString()), 0.001);

        $this->assertBooksBalance();
    }

    public function test_reopen_refuses_if_period_is_open(): void
    {
        $period = AccountingPeriod::forDate('2026-04-15');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not closed');

        $this->svc->reopen($period);
    }

    public function test_close_records_period_as_source(): void
    {
        $period = AccountingPeriod::forDate('2026-04-15');

        $cash = $this->account('1110');
        $revenue = $this->account('4110');

        $this->postEntry('2026-04-10', $cash->id, $revenue->id, 100.000);
        $entry = $this->svc->close($period);

        $this->assertSame(AccountingPeriod::class, $entry->source_type);
        $this->assertSame($period->id, $entry->source_id);
    }
}
