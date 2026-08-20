<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\Partner;
use Illuminate\Database\Seeder;

/**
 * Pins the per-entity GL links the posting engine reads before falling back to
 * the global posting map (see App\Services\Accounting\ChartOfAccounts):
 *
 *   branches.account_id -> that branch's own cash / operating account
 *   partners.account_id -> the clinic's default (services) revenue account
 *
 * The engine already resolves both of these implicitly — a branch's cash falls
 * through to the "1110-<branchId>" sub-account and services revenue falls
 * through to 4110 — so this seeder changes no posting behaviour today. What it
 * buys is that the wiring becomes explicit and editable in the admin UI instead
 * of depending on a code-naming convention.
 *
 * Idempotent, and deliberately only fills NULLs: once an accountant has pointed
 * a branch or clinic at a different account, re-running this leaves it alone.
 */
class PostingEntityLinksSeeder extends Seeder
{
    /** Chart code for the clinic's default clinical-services revenue account. */
    private const CLINIC_REVENUE_CODE = '4110';

    /** Parent code the per-branch cash sub-accounts hang off. */
    private const CASH_PARENT_CODE = '1110';

    public function run(): void
    {
        $this->linkBranches();
        $this->linkPartners();
    }

    /**
     * Each branch gets its own cash-on-hand sub-account ("1110-<id>"), matching
     * what AccountingChartOfAccountsSeeder creates. Created here too so a branch
     * added after the chart was seeded still gets one.
     */
    private function linkBranches(): void
    {
        $cashParent = Account::where('code', self::CASH_PARENT_CODE)->first();

        if (! $cashParent) {
            $this->command?->warn('Cash parent '.self::CASH_PARENT_CODE.' missing — run AccountingChartOfAccountsSeeder first.');

            return;
        }

        $linked = 0;

        foreach (Branch::query()->get() as $branch) {
            $cash = Account::firstOrCreate(
                ['code' => self::CASH_PARENT_CODE.'-'.$branch->id],
                [
                    'name' => 'Cash on Hand — '.($branch->localized_name ?? ('Branch '.$branch->id)),
                    'type' => Account::TYPE_ASSET,
                    'parent_id' => $cashParent->id,
                    'branch_id' => $branch->id,
                    'currency' => 'KWD',
                    'is_active' => true,
                    'is_system' => true,
                ]
            );

            if ($branch->account_id === null) {
                $branch->forceFill(['account_id' => $cash->id])->save();
                $linked++;
            }
        }

        $this->command?->info("Linked {$linked} branch(es) to their cash account.");
    }

    /** Clinic-level default revenue account for services / consultation income. */
    private function linkPartners(): void
    {
        $revenue = Account::where('code', self::CLINIC_REVENUE_CODE)->first();

        if (! $revenue) {
            $this->command?->warn('Revenue account '.self::CLINIC_REVENUE_CODE.' missing — run AccountingChartOfAccountsSeeder first.');

            return;
        }

        $linked = 0;

        foreach (Partner::query()->whereNull('account_id')->get() as $partner) {
            $partner->forceFill(['account_id' => $revenue->id])->save();
            $linked++;
        }

        $this->command?->info("Linked {$linked} clinic(s) to revenue account {$revenue->code}.");
    }
}
