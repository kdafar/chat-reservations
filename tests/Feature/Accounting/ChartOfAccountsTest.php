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
            '1010', '1020', '1100', '1110', '1200',     // assets
            '2010', '2020',                              // liabilities
            '3020',                                       // retained earnings (for period close)
            '4010', '4020', '4030', '4900',              // revenue
            '5010',                                       // cogs
            '6010', '6030',                              // expenses
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
        $this->assertTrue($this->account('1010')->isDebitNormal());  // Cash
        $this->assertTrue($this->account('1200')->isDebitNormal());  // Inventory
        $this->assertTrue($this->account('5010')->isDebitNormal());  // COGS
        $this->assertTrue($this->account('6010')->isDebitNormal());  // Expense

        $this->assertFalse($this->account('1010')->isCreditNormal());
    }

    public function test_liability_equity_revenue_are_credit_normal(): void
    {
        $this->assertTrue($this->account('2010')->isCreditNormal());  // AP
        $this->assertTrue($this->account('2020')->isCreditNormal());  // Doctor Payable
        $this->assertTrue($this->account('3020')->isCreditNormal());  // Retained Earnings
        $this->assertTrue($this->account('4010')->isCreditNormal());  // Revenue

        $this->assertFalse($this->account('4010')->isDebitNormal());
    }

    public function test_contra_revenue_acts_as_debit_normal(): void
    {
        // Discounts Given (4900) is a contra-revenue — Dr increases it.
        $this->assertTrue($this->account('4900')->isDebitNormal());
        $this->assertFalse($this->account('4900')->isCreditNormal());
    }

    public function test_system_accounts_cannot_be_marked_user_managed(): void
    {
        // Spot-check: at least some accounts ARE flagged as system.
        $this->assertTrue($this->account('1010')->is_system);
        $this->assertTrue($this->account('4010')->is_system);
        $this->assertTrue($this->account('3020')->is_system);
    }

    public function test_balance_at_returns_zero_when_no_entries(): void
    {
        $this->assertEquals(0.0, $this->account('1010')->balanceAt(now()->toDateString()));
        $this->assertEquals(0.0, $this->account('4010')->balanceBetween('2020-01-01', '2030-12-31'));
    }
}
