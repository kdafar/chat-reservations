<?php

use App\Models\Accounting\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll accounting wiring:
 *
 *  - new GL accounts: Staff Loans Receivable (1130), End-of-Service Provision
 *    (2040, liability) and End-of-Service Expense (6016). 6015 Staff Salaries
 *    and 2030 Staff Salaries Payable already exist from the COA seeder.
 *  - doctor_compensation_ledgers.settled_payroll_run_id: marks which payroll
 *    run paid out a doctor's accrued commission, so we never pay it twice and
 *    can pull the as-yet-unsettled cuts when building a run.
 *
 * Account upserts mirror AccountingChartOfAccountsSeeder (the source of truth);
 * doing them here too keeps the GL functional immediately after migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doctor_compensation_ledgers')
            && ! Schema::hasColumn('doctor_compensation_ledgers', 'settled_payroll_run_id')) {
            Schema::table('doctor_compensation_ledgers', function (Blueprint $table) {
                $table->unsignedBigInteger('settled_payroll_run_id')->nullable()->index();
            });
        }

        if (! class_exists(Account::class)) {
            return;
        }

        $parentId = fn (string $code) => Account::where('code', $code)->value('id');

        $accounts = [
            ['1130', 'Staff Loans & Advances Receivable', Account::TYPE_ASSET,     $parentId('1100')],
            ['2040', 'End-of-Service Provision',          Account::TYPE_LIABILITY, $parentId('2000')],
            ['6016', 'End-of-Service Expense',            Account::TYPE_EXPENSE,   $parentId('6000')],
        ];

        foreach ($accounts as [$code, $name, $type, $parent]) {
            Account::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => $parent,
                    'currency' => 'KWD',
                    'is_active' => true,
                    'is_system' => false,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('doctor_compensation_ledgers', 'settled_payroll_run_id')) {
            Schema::table('doctor_compensation_ledgers', function (Blueprint $table) {
                $table->dropColumn('settled_payroll_run_id');
            });
        }
        // Leave the GL accounts in place — they may carry posted history.
    }
};
