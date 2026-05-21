<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Expense;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        app(\App\Services\Accounting\ChartOfAccounts::class)->refresh();
    }

    public function test_draft_expense_does_not_post(): void
    {
        $vendor = Vendor::create(['name' => 'Landlord', 'is_active' => true]);
        $rent = $this->account('6030');
        $cash = $this->account('1010');

        $before = JournalEntry::count();
        $expense = Expense::create([
            'expense_date' => now()->toDateString(),
            'vendor_id' => $vendor->id,
            'account_id' => $rent->id,
            'payment_account_id' => $cash->id,
            'amount' => 500.000,
            'description' => 'Rent — March',
            'status' => 'draft',
        ]);

        $this->assertSame($before, JournalEntry::count(), 'Drafts must NOT auto-post');
        $this->assertNull($expense->journal_entry_id);
        $this->assertSame('draft', $expense->status);
    }

    public function test_post_creates_balanced_journal_entry(): void
    {
        $vendor = Vendor::create(['name' => 'Landlord', 'is_active' => true]);
        $rent = $this->account('6030');
        $cash = $this->account('1010');

        $expense = Expense::create([
            'expense_date' => now()->toDateString(),
            'vendor_id' => $vendor->id,
            'account_id' => $rent->id,
            'payment_account_id' => $cash->id,
            'amount' => 500.000,
            'description' => 'Rent',
            'status' => 'draft',
        ]);

        $expense->post();

        $expense->refresh();
        $this->assertSame('posted', $expense->status);
        $this->assertNotNull($expense->journal_entry_id);
        $this->assertNotNull($expense->posted_at);

        $entry = JournalEntry::find($expense->journal_entry_id);
        $this->assertEqualsWithDelta(500.0, $entry->totalDebit(), 0.001);
        $this->assertEqualsWithDelta(500.0, $entry->totalCredit(), 0.001);

        // Rent expense debit, Cash credit
        $debitLine = $entry->lines->where('debit', '>', 0)->first();
        $creditLine = $entry->lines->where('credit', '>', 0)->first();
        $this->assertSame('6030', $debitLine->account->code);
        $this->assertSame('1010', $creditLine->account->code);

        $this->assertBooksBalance();
    }

    public function test_post_without_payment_account_uses_accounts_payable(): void
    {
        $vendor = Vendor::create(['name' => 'Supplier', 'is_active' => true]);
        $rent = $this->account('6030');

        $expense = Expense::create([
            'expense_date' => now()->toDateString(),
            'vendor_id' => $vendor->id,
            'account_id' => $rent->id,
            'payment_account_id' => null,  // outstanding bill
            'amount' => 200.000,
            'description' => 'Unpaid bill',
            'status' => 'draft',
        ]);

        $expense->post();
        $entry = JournalEntry::find($expense->journal_entry_id);

        $creditLine = $entry->lines->where('credit', '>', 0)->first();
        $this->assertSame('2010', $creditLine->account->code, 'Unpaid expenses must credit AP (2010)');
    }

    public function test_post_is_idempotent(): void
    {
        $vendor = Vendor::create(['name' => 'V', 'is_active' => true]);
        $expense = Expense::create([
            'expense_date' => now()->toDateString(),
            'vendor_id' => $vendor->id,
            'account_id' => $this->account('6030')->id,
            'payment_account_id' => $this->account('1010')->id,
            'amount' => 100.000,
            'description' => 'x',
            'status' => 'draft',
        ]);

        $expense->post();
        $firstJeId = $expense->journal_entry_id;

        $expense->post(); // second call

        $this->assertSame($firstJeId, $expense->journal_entry_id);
        $this->assertSame(1, JournalEntry::where('source_type', Expense::class)
            ->where('source_id', $expense->id)->count());
    }

    public function test_code_is_auto_generated(): void
    {
        $vendor = Vendor::create(['name' => 'V', 'is_active' => true]);
        $expense = Expense::create([
            'expense_date' => '2026-05-20',
            'vendor_id' => $vendor->id,
            'account_id' => $this->account('6030')->id,
            'amount' => 100.000,
            'description' => 'x',
            'status' => 'draft',
        ]);

        $this->assertNotEmpty($expense->code);
        $this->assertStringStartsWith('EXP-', $expense->code);
    }

    public function test_void_reverses_journal_entry(): void
    {
        $vendor = Vendor::create(['name' => 'V', 'is_active' => true]);
        $expense = Expense::create([
            'expense_date' => now()->toDateString(),
            'vendor_id' => $vendor->id,
            'account_id' => $this->account('6030')->id,
            'payment_account_id' => $this->account('1010')->id,
            'amount' => 150.000,
            'description' => 'will be voided',
            'status' => 'draft',
        ]);
        $expense->post();
        $originalJeId = $expense->journal_entry_id;

        $expense->void();

        $expense->refresh();
        $this->assertSame('void', $expense->status);

        // Original JE is reversed
        $original = JournalEntry::find($originalJeId);
        $this->assertSame(JournalEntry::STATUS_REVERSED, $original->status);

        // Net effect on the books: zero
        $this->assertEqualsWithDelta(0.0, $this->account('6030')->balanceAt(now()->toDateString()), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->account('1010')->balanceAt(now()->toDateString()), 0.001);

        $this->assertBooksBalance();
    }
}
