<?php

namespace Database\Seeders\Demo;

use App\Models\Visit;
use App\Services\Clinic\VisitCostingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts real discounts on the visits.
 *
 * The volume seeder set `discount_type = 'amount'` but never wrote
 * `discount_value`. VisitDiscountService resolves the discount from those two
 * fields together, so every recompute quietly resolved to zero and the estate
 * ended up with 183 KWD of discount across 4,800 visits — 0.02% of billing.
 * The discounts report was reading that correctly and had nothing to show.
 *
 * Rewrites both fields on a realistic share of visits and re-costs them, so the
 * discount flows through to the contra-revenue account the same way a real
 * front-desk discount would.
 */
class DemoDiscountSeeder extends Seeder
{
    /** Share of completed visits that carry a negotiated discount. */
    protected float $rate = 0.14;

    public function run(): void
    {
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $existing = (float) DB::table('visits')->where('status', 'completed')->sum('discount_total');
        if ($existing > 20000) {
            $this->command?->warn('DemoDiscountSeeder: discounts already applied — skipping.');

            return;
        }

        $costing = app(VisitCostingService::class);
        $applied = 0;
        $total = 0.0;

        Visit::query()->withoutGlobalScopes()
            ->where('status', Visit::STATUS_COMPLETED)
            ->whereNotNull('computed_at')
            ->orderBy('id')
            ->chunkById(300, function ($visits) use ($costing, &$applied, &$total) {
                foreach ($visits as $visit) {
                    if (mt_rand() / mt_getrandmax() >= $this->rate) {
                        continue;
                    }

                    // Percentage discounts are the staff-loyalty and campaign
                    // kind; flat amounts are the goodwill gesture at the desk.
                    if ($applied % 3 === 0) {
                        $type = 'percent';
                        $value = [5, 10, 10, 15, 20, 25][$applied % 6];
                    } else {
                        $type = 'amount';
                        $value = [5.000, 10.000, 15.000, 20.000, 25.000, 30.000][$applied % 6];
                    }

                    $visit->forceFill(['discount_type' => $type, 'discount_value' => $value])->saveQuietly();

                    Carbon::setTestNow(Carbon::parse($visit->computed_at));
                    $costing->compute($visit->refresh(), 0);
                    Carbon::setTestNow();

                    $applied++;
                    $total += (float) $visit->refresh()->discount_total;

                    if ($applied % 200 === 0) {
                        $this->command?->info("  {$applied} visits discounted");
                    }
                }
            });

        Carbon::setTestNow();
        $this->command?->info(sprintf(
            'DemoDiscountSeeder: applied discounts to %d visits totalling %s KWD.',
            $applied, number_format($total, 3),
        ));
    }
}
