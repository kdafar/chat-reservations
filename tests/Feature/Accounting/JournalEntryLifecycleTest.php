<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class JournalEntryLifecycleTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
    }

    /** Helper: build a balanced draft with two lines. */
    private function makeDraft(float $amount = 100.000): JournalEntry
    {
        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'narration' => 'Test entry',
            'status' => JournalEntry::STATUS_DRAFT,
            'currency' => 'KWD',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->account('1010')->id,
            'debit' => $amount,
            'credit' => 0,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->account('4010')->id,
            'debit' => 0,
            'credit' => $amount,
        ]);

        return $entry->refresh();
    }

    public function test_balanced_draft_posts_successfully(): void
    {
        $entry = $this->makeDraft(100.000);

        $entry->post();

        $entry->refresh();
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertNotNull($entry->code);
        $this->assertStringStartsWith('JE-', $entry->code);
        $this->assertTrue($entry->isBalanced());
    }

    public function test_unbalanced_entry_rejects_posting(): void
    {
        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'narration' => 'Unbalanced',
            'status' => JournalEntry::STATUS_DRAFT,
            'currency' => 'KWD',
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->account('1010')->id,
            'debit' => 50.000, 'credit' => 0,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->account('4010')->id,
            'debit' => 0, 'credit' => 30.000,
        ]);
        $entry->refresh();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unbalanced');

        $entry->post();
    }

    public function test_post_is_idempotent(): void
    {
        $entry = $this->makeDraft(50.000);
        $entry->post();
        $postedAt = $entry->posted_at;
        $code = $entry->code;

        $entry->post(); // second call should be no-op

        $entry->refresh();
        $this->assertSame($code, $entry->code);
        $this->assertEquals($postedAt->timestamp, $entry->posted_at->timestamp);
    }

    public function test_post_attaches_to_correct_accounting_period(): void
    {
        $entry = $this->makeDraft(75.000);
        $entry->forceFill(['entry_date' => '2026-03-15'])->save();

        $entry->post();
        $entry->refresh();

        $this->assertNotNull($entry->accounting_period_id);
        $this->assertEquals('2026-03', $entry->period->code);
    }

    public function test_post_refuses_when_period_is_closed(): void
    {
        $period = AccountingPeriod::forDate('2026-03-15');
        $period->forceFill([
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ])->save();

        $entry = $this->makeDraft(20.000);
        $entry->forceFill(['entry_date' => '2026-03-15'])->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('closed');

        $entry->post();
    }

    public function test_reverse_creates_offsetting_entry_and_flips_original(): void
    {
        $entry = $this->makeDraft(100.000);
        $entry->post();

        $reversal = $entry->reverse(null, 'test reversal');

        $entry->refresh();
        $this->assertSame(JournalEntry::STATUS_REVERSED, $entry->status);
        $this->assertEquals($reversal->id, $entry->reversed_by_id);

        // Reversal lines swap debit/credit
        $this->assertSame(JournalEntry::STATUS_POSTED, $reversal->status);
        $this->assertCount(2, $reversal->lines);

        // Net effect on the GL: zero (original Dr 100 / Cr 100 + reversal Cr 100 / Dr 100)
        $this->assertBooksBalance();
        $this->assertEquals(0.0, $this->account('1010')->balanceAt(now()->toDateString()));
    }

    public function test_cannot_reverse_a_draft(): void
    {
        $entry = $this->makeDraft(40.000);
        // Not posted

        $this->expectException(\RuntimeException::class);
        $entry->reverse();
    }

    public function test_cannot_double_reverse_an_entry(): void
    {
        $entry = $this->makeDraft(60.000);
        $entry->post();
        $entry->reverse(null, 'first');

        $this->expectException(\RuntimeException::class);
        $entry->reverse(null, 'second');
    }

    public function test_balance_validation_tolerates_subkwd_rounding(): void
    {
        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'narration' => 'Rounding',
            'status' => JournalEntry::STATUS_DRAFT,
            'currency' => 'KWD',
        ]);
        // 0.0001 difference — comfortably below storage precision; should be balanced.
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->account('1010')->id,
            'debit' => 100.0001, 'credit' => 0,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $this->account('4010')->id,
            'debit' => 0, 'credit' => 100.0000,
        ]);
        $entry->refresh();

        // Should NOT throw — within tolerance.
        $entry->post();

        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->refresh()->status);
    }
}
