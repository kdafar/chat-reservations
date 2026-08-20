<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Strips the tenant-specific wording that the shipped Chart of Accounts
 * inherited from the clinic it was first written for.
 *
 * Five accounts were named after one clinic's doctor and bank ("… — Dr. Kareem",
 * "Bank — CBK Current Account"). Those shipped to every install, so a new clinic
 * opened its ledger and found another clinic's staff in it.
 *
 * AccountingChartOfAccountsSeeder now carries the generic wording, but it only
 * seeds names on CREATE — renaming there does not reach a database that already
 * has these rows. Hence this one-time correction.
 *
 * Renames only where the name is still EXACTLY the old shipped string, so a
 * clinic that has already relabelled an account is left untouched. Account
 * CODES are the posting engine's contract and are deliberately not changed —
 * nothing about existing journal entries or the posting map is affected.
 */
return new class extends Migration
{
    /** old English name => [new English, new Arabic] */
    private const RENAMES = [
        'Bank — CBK Current Account' => ['Bank — Current Account', 'البنك — الحساب الجاري'],
        'Dermatology & Aesthetics — Dr. Kareem' => ['Clinical Services — General', 'إيرادات الخدمات الإكلينيكية — عام'],
        'Dr. Kareem Cost (Direct)' => ['Lead Doctor Cost (Direct)', 'تكلفة الطبيب الرئيسي (مباشرة)'],
        'Sales Commission — Dr. Kareem (10%)' => ['Sales Commission — Doctors', 'عمولة مبيعات — الأطباء'],
        'Marketing — Dr. Kareem' => ['Marketing — Doctor Personal Brand', 'تسويق — العلامة الشخصية للطبيب'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => [$en, $ar]) {
            DB::table('chart_of_accounts')
                ->where('name', $old)
                ->update(['name' => $en, 'description' => $ar, 'updated_at' => now()]);
        }
    }

    /**
     * Deliberately irreversible: rolling back would write one clinic's doctor
     * and bank into every other clinic's ledger.
     */
    public function down(): void
    {
        // no-op
    }
};
