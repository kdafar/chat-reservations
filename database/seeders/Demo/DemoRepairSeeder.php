<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\Inpatient\Bed;
use App\Models\Inpatient\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Repairs referential drift left behind by earlier reseeds before the demo
 * reporting data is generated.
 *
 * The inpatient module (16 wards / 64 beds) and 5 branch-scoped GL accounts
 * still point at branches 4/5/6/20, which no longer exist — the live estate is
 * branches 22–33 from EurekaDemoSeeder. Anything reading those rows through a
 * branch join returns nothing, which is why Inpatient Reports renders blank.
 */
class DemoRepairSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()->orderBy('id')->get(['id', 'partner_id', 'name']);
        if ($branches->isEmpty()) {
            $this->command?->warn('DemoRepairSeeder: no branches — nothing to repair.');

            return;
        }
        $liveIds = $branches->pluck('id')->all();

        $this->repointWards($branches, $liveIds);
        $this->repointAccounts($branches, $liveIds);
    }

    /** Spread orphaned wards (and their beds) across the live branches. */
    protected function repointWards($branches, array $liveIds): void
    {
        $orphans = Ward::query()->whereNotIn('branch_id', $liveIds)->orderBy('id')->get();
        if ($orphans->isEmpty()) {
            return;
        }

        $i = 0;
        foreach ($orphans as $ward) {
            $branch = $branches[$i % $branches->count()];
            $i++;

            $ward->branch_id = $branch->id;
            $ward->partner_id = $branch->partner_id;
            $ward->save();

            Bed::query()->where('ward_id', $ward->id)->update([
                'branch_id' => $branch->id,
                // Admissions are seeded next and will claim the beds they need;
                // start from a clean slate so occupancy is never phantom.
                'status' => 'available',
            ]);
        }

        $this->command?->info("DemoRepairSeeder: repointed {$orphans->count()} wards + their beds onto live branches.");
    }

    /**
     * Branch-scoped cash accounts named after dead branches. Repoint and rename
     * so the per-branch cash columns on the financial reports resolve.
     */
    protected function repointAccounts($branches, array $liveIds): void
    {
        $orphans = Account::query()->whereNotNull('branch_id')->whereNotIn('branch_id', $liveIds)->orderBy('id')->get();
        if ($orphans->isEmpty()) {
            return;
        }

        $i = 0;
        foreach ($orphans as $account) {
            $branch = $branches[$i % $branches->count()];
            $i++;

            $label = $this->branchLabel($branch);
            $account->branch_id = $branch->id;
            // Only rewrite the trailing "— Old Branch" segment; keep the account's own name.
            if (str_contains((string) $account->name, '—')) {
                $account->name = trim(explode('—', (string) $account->name)[0]).' — '.$label;
            }
            $account->save();

            // The code carries the branch id as a suffix (e.g. 1110-4) — realign it.
            if (preg_match('/^(\d{4})-(\d+)$/', (string) $account->code, $m)) {
                $newCode = $m[1].'-'.$branch->id;
                $taken = Account::query()->where('code', $newCode)->where('id', '!=', $account->id)->exists();
                if (! $taken) {
                    DB::table('chart_of_accounts')->where('id', $account->id)->update(['code' => $newCode]);
                }
            }
        }

        $this->command?->info("DemoRepairSeeder: repointed {$orphans->count()} branch-scoped GL accounts.");
    }

    protected function branchLabel(Branch $branch): string
    {
        $name = $branch->name;
        if (is_string($name) && str_starts_with(trim($name), '{')) {
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                return $decoded['en'] ?? reset($decoded) ?: ('Branch #'.$branch->id);
            }
        }

        return is_string($name) && $name !== '' ? $name : ('Branch #'.$branch->id);
    }
}
