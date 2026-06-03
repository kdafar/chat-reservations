<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * Seeds the standard Kuwait medical-clinic Chart of Accounts.
 *
 * Conventions:
 *   1xxx Assets       Cash, Bank, AR, Inventory, Equipment
 *   2xxx Liabilities  AP, Doctor Payable, Salaries Payable, Customer Deposits
 *   3xxx Equity       Owner Capital, Retained Earnings
 *   4xxx Revenue      Consultation, Packages, Items, Other, Discount (contra)
 *   5xxx COGS         Cost of Items Sold
 *   6xxx Expenses     Doctor Comp, Staff Salaries, Rent, Utilities, Insurance,
 *                     Marketing, Office Supplies, Bank Fees, Other
 *
 * Idempotent — re-run upgrades but won't duplicate.
 */
class AccountingChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Chart of Accounts...');

        // The flat top-level Chart of Accounts. parent_code refers to another
        // entry in this same list; resolved into FK after the first pass.
        $accounts = [
            // ===== ASSETS (1xxx) =====
            ['1000', 'Assets',                                Account::TYPE_ASSET, null, true],
            ['1010', 'Cash on Hand',                          Account::TYPE_ASSET, '1000', true],
            ['1020', 'Bank Accounts',                         Account::TYPE_ASSET, '1000', true],
            ['1021', 'Bank - KFH (Kuwait Finance House)',     Account::TYPE_ASSET, '1020', false],
            ['1022', 'Bank - NBK (National Bank of Kuwait)',  Account::TYPE_ASSET, '1020', false],
            ['1100', 'Accounts Receivable',                   Account::TYPE_ASSET, '1000', true],
            ['1110', 'Patient Receivables',                   Account::TYPE_ASSET, '1100', true],
            ['1120', 'Insurance Receivables',                 Account::TYPE_ASSET, '1100', false],
            ['1200', 'Inventory - Medical Supplies',          Account::TYPE_ASSET, '1000', true],
            ['1300', 'Prepaid Expenses',                      Account::TYPE_ASSET, '1000', false],
            ['1400', 'Fixed Assets - Equipment',              Account::TYPE_ASSET, '1000', false],
            ['1410', 'Accumulated Depreciation',              Account::TYPE_CONTRA_ASSET, '1400', false],

            // ===== LIABILITIES (2xxx) =====
            ['2000', 'Liabilities',                           Account::TYPE_LIABILITY, null, true],
            ['2010', 'Accounts Payable',                      Account::TYPE_LIABILITY, '2000', true],
            ['2020', 'Doctor Payable',                        Account::TYPE_LIABILITY, '2000', true],
            ['2030', 'Staff Salaries Payable',                Account::TYPE_LIABILITY, '2000', false],
            ['2100', 'Customer Deposits',                     Account::TYPE_LIABILITY, '2000', false],

            // ===== EQUITY (3xxx) =====
            ['3000', 'Equity',                                Account::TYPE_EQUITY, null, true],
            ['3010', 'Owner Capital',                         Account::TYPE_EQUITY, '3000', true],
            ['3020', 'Retained Earnings',                     Account::TYPE_EQUITY, '3000', true],

            // ===== REVENUE (4xxx) =====
            ['4000', 'Revenue',                               Account::TYPE_REVENUE, null, true],
            ['4010', 'Consultation Revenue',                  Account::TYPE_REVENUE, '4000', true],
            ['4020', 'Package & Services Revenue',            Account::TYPE_REVENUE, '4000', true],
            ['4030', 'Pharmacy / Items Revenue',              Account::TYPE_REVENUE, '4000', true],
            ['4040', 'Other Income',                          Account::TYPE_REVENUE, '4000', false],
            ['4900', 'Discounts Given',                       Account::TYPE_CONTRA_REVENUE, '4000', true],

            // ===== COGS (5xxx) =====
            ['5000', 'Cost of Sales',                         Account::TYPE_COGS, null, true],
            ['5010', 'Cost of Items Sold',                    Account::TYPE_COGS, '5000', true],

            // ===== EXPENSES (6xxx) =====
            ['6000', 'Operating Expenses',                    Account::TYPE_EXPENSE, null, true],
            ['6010', 'Doctor Compensation Expense',           Account::TYPE_EXPENSE, '6000', true],
            // 6020 was historically 'Staff Salaries'; repurposed to 'Bad Debt Expense'
            // by migration 2026_05_24_100010 to back the insurance claims write-off
            // auto-posting. Staff Salaries moved to 6015 to free the slot.
            ['6015', 'Staff Salaries',                        Account::TYPE_EXPENSE, '6000', false],
            ['6020', 'Bad Debt Expense',                      Account::TYPE_EXPENSE, '6000', true],
            ['6030', 'Rent',                                  Account::TYPE_EXPENSE, '6000', false],
            ['6040', 'Utilities',                             Account::TYPE_EXPENSE, '6000', false],
            ['6050', 'Insurance',                             Account::TYPE_EXPENSE, '6000', false],
            ['6060', 'Marketing & Advertising',               Account::TYPE_EXPENSE, '6000', false],
            ['6070', 'Office & Medical Supplies',             Account::TYPE_EXPENSE, '6000', false],
            ['6080', 'Bank & Gateway Fees',                   Account::TYPE_EXPENSE, '6000', false],
            ['6090', 'Other Expenses',                        Account::TYPE_EXPENSE, '6000', false],
        ];

        // Pass 1: upsert by code without parent FK
        foreach ($accounts as [$code, $name, $type, $_, $isSystem]) {
            Account::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'currency' => 'KWD',
                    'is_active' => true,
                    'is_system' => $isSystem,
                ]
            );
        }

        // Pass 2: wire parents
        $byCode = Account::query()->get()->keyBy('code');
        foreach ($accounts as [$code, $_, $__, $parentCode, $___]) {
            if ($parentCode === null) {
                continue;
            }
            $parent = $byCode->get($parentCode);
            $self = $byCode->get($code);
            if ($parent && $self && $self->parent_id !== $parent->id) {
                $self->update(['parent_id' => $parent->id]);
            }
        }

        // Pass 3: per-branch cash sub-accounts ("Cash - Branch 4" with code 1010-4)
        $cashParent = Account::where('code', '1010')->first();
        if ($cashParent) {
            foreach (Branch::query()->get() as $branch) {
                $code = '1010-'.$branch->id;
                Account::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => 'Cash - '.($branch->localized_name ?? ('Branch '.$branch->id)),
                        'type' => Account::TYPE_ASSET,
                        'parent_id' => $cashParent->id,
                        'branch_id' => $branch->id,
                        'currency' => 'KWD',
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }
        }

        $this->command->info('Seeded '.Account::count().' accounts.');
    }
}
