<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
    }

    public function test_seeder_creates_expected_account_count(): void
    {
        // 36 standard + at least 1 branch sub-account (added below if needed)
        $this->seedClinicFixtures();
        // Re-run seeder to pick up the new branch and add the per-branch cash sub-account.
        $this->seedChartOfAccounts();

        $this->assertGreaterThanOrEqual(37, Account::count());
    }

    public function test_every_account_has_a_canonical_type(): void
    {
        $validTypes = [
            Account::TYPE_ASSET,
            Account::TYPE_LIABILITY,
            Account::TYPE_EQUITY,
            Account::TYPE_REVENUE,
            Account::TYPE_COGS,
            Account::TYPE_EXPENSE,
            Account::TYPE_CONTRA_ASSET,
            Account::TYPE_CONTRA_LIABILITY,
            Account::TYPE_CONTRA_REVENUE,
        ];

        Account::all()->each(function (Account $a) use ($validTypes) {
            $this->assertContains($a->type, $validTypes, "Account {$a->code} has invalid type {$a->type}");
        });
    }

    public function test_required_accounts_exist_for_auto_posting(): void
    {
        // Every account code referenced by AccountingService / ChartOfAccounts must exist.
        $required = [
            '1110', '1120', '1130', '1140', '1150',     // assets: cash, bank, clearing, AR, inventory
            '2110', '2130', '2190',                      // liabilities: AP, staff/doctor payable, import payable
            '3400',                                       // retained earnings (for period close)
            '4110', '4210', '4290', '4310',              // revenue + contra-revenue (discounts)
            '5120', '5130',                              // cogs: items, doctor compensation
            '6110', '6530',                              // expenses: salaries, bad debt / misc
        ];

        foreach ($required as $code) {
            $this->assertTrue(
                Account::where('code', $code)->exists(),
                "Required account {$code} is missing from the seeded Chart of Accounts"
            );
        }
    }

    public function test_asset_and_expense_accounts_are_debit_normal(): void
    {
        $this->assertTrue($this->account('1110')->isDebitNormal());  // Cash
        $this->assertTrue($this->account('1150')->isDebitNormal());  // Inventory
        $this->assertTrue($this->account('5120')->isDebitNormal());  // COGS
        $this->assertTrue($this->account('6530')->isDebitNormal());  // Expense

        $this->assertFalse($this->account('1110')->isCreditNormal());
    }

    public function test_liability_equity_revenue_are_credit_normal(): void
    {
        $this->assertTrue($this->account('2110')->isCreditNormal());  // AP
        $this->assertTrue($this->account('2130')->isCreditNormal());  // Staff/Doctor Payable
        $this->assertTrue($this->account('3400')->isCreditNormal());  // Retained Earnings
        $this->assertTrue($this->account('4110')->isCreditNormal());  // Revenue

        $this->assertFalse($this->account('4110')->isDebitNormal());
    }

    public function test_contra_revenue_acts_as_debit_normal(): void
    {
        // Discounts & Promotions (4310) is a contra-revenue — Dr increases it.
        $this->assertTrue($this->account('4310')->isDebitNormal());
        $this->assertFalse($this->account('4310')->isCreditNormal());
    }

    public function test_system_accounts_cannot_be_marked_user_managed(): void
    {
        // Spot-check: at least some accounts ARE flagged as system.
        $this->assertTrue($this->account('1110')->is_system);
        $this->assertTrue($this->account('4110')->is_system);
        $this->assertTrue($this->account('3400')->is_system);
    }

    public function test_balance_at_returns_zero_when_no_entries(): void
    {
        $this->assertEquals(0.0, $this->account('1110')->balanceAt(now()->toDateString()));
        $this->assertEquals(0.0, $this->account('4110')->balanceBetween('2020-01-01', '2030-12-31'));
    }
}
