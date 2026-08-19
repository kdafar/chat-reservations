<?php

namespace Database\Seeders\Demo;

use App\Models\ClinicPackage;
use App\Models\Visit;
use App\Models\VisitPackage;
use App\Services\Clinic\VisitCostingService;
use App\Services\Clinic\VisitPackageService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sells a package on a slice of the visits that already exist.
 *
 * Runs standalone so the package line can be backfilled without regenerating
 * six months of clinical history. Each sale goes through VisitPackageService
 * and then re-costs the visit, so packages_price_total, the revenue accrual and
 * the doctor's cut all move together.
 */
class DemoPackageSalesSeeder extends Seeder
{
    /** Share of completed visits that also buy a package. */
    protected float $rate = 0.17;

    public function run(): void
    {
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        if (VisitPackage::query()->withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoPackageSalesSeeder: package sales already exist — skipping.');

            return;
        }

        $packages = ClinicPackage::query()->withoutGlobalScopes()->where('is_active', true)->get();
        if ($packages->isEmpty()) {
            $this->command?->warn('DemoPackageSalesSeeder: no active packages.');

            return;
        }

        $service = app(VisitPackageService::class);
        $costing = app(VisitCostingService::class);

        $visits = Visit::query()->withoutGlobalScopes()
            ->where('status', Visit::STATUS_COMPLETED)
            ->whereNotNull('computed_at')
            ->with('branch:id,partner_id')
            ->orderBy('id')
            ->get(['id', 'branch_id', 'computed_at', 'status']);

        $sold = 0;
        $failed = 0;

        foreach ($visits as $i => $visit) {
            if (mt_rand() / mt_getrandmax() >= $this->rate) {
                continue;
            }

            // Only packages this branch is actually allowed to sell.
            $eligible = $packages->filter(
                fn ($p) => (! $p->partner_id || (int) $p->partner_id === (int) ($visit->branch?->partner_id))
                    && (! $p->branch_id || (int) $p->branch_id === (int) $visit->branch_id)
            )->values();
            if ($eligible->isEmpty()) {
                continue;
            }

            // Backdate so the sale lands on the visit's own date rather than today.
            Carbon::setTestNow(Carbon::parse($visit->computed_at));

            try {
                $service->applyPackagesOnly(
                    $visit,
                    [['clinic_package_id' => $eligible[$i % $eligible->count()]->id, 'qty' => random_int(1, 100) <= 82 ? 1 : 2]],
                    0,
                    'demo package sale',
                );
                $costing->compute($visit->refresh(), 0);
                $sold++;
            } catch (\Throwable $e) {
                $failed++;
                if ($failed <= 3) {
                    $this->command?->warn("  visit #{$visit->id}: {$e->getMessage()}");
                }
            }

            Carbon::setTestNow();

            if ($sold > 0 && $sold % 150 === 0) {
                $this->command?->info("  {$sold} packages sold so far");
            }
        }

        Carbon::setTestNow();
        $this->command?->info("DemoPackageSalesSeeder: sold {$sold} packages ({$failed} failed).");
    }
}
