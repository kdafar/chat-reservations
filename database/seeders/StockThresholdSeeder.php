<?php

namespace Database\Seeders;

use App\Models\ClinicItemStock;
use Illuminate\Database\Seeder;

/**
 * Re-order points for stock on hand.
 *
 * Without min_qty_threshold_base the low-stock machinery is inert: the Stock
 * screen's "Low stock" filter matches nothing and ClinicStockMovementObserver
 * never raises LowStockNotification, because both compare on-hand against a
 * threshold that is NULL everywhere.
 *
 * Policy: an item's par level is its median on-hand across the branches that
 * carry it (the branches are stocked alike, so the median is a fair "normal
 * holding"), and the re-order point is 40% of par — the level at which a
 * clinic would order more. Rounded to half units, never below 1, so the
 * numbers read like something a storekeeper would write down.
 *
 * Re-runnable, and only fills thresholds that are still unset unless run with
 * `--force`-style intent (pass true to overwrite).
 */
class StockThresholdSeeder extends Seeder
{
    /** Fraction of par level at which the item should be re-ordered. */
    private const REORDER_FACTOR = 0.4;

    public function run(bool $overwrite = false): void
    {
        $rows = ClinicItemStock::query()->withoutGlobalScopes()->get();

        if ($rows->isEmpty()) {
            $this->command?->warn('No stock rows — nothing to threshold.');

            return;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($rows->groupBy('clinic_item_id') as $group) {
            $par = $this->median($group->pluck('qty_on_hand_base')->map(fn ($q) => (float) $q)->all());
            $threshold = $this->roundToHalf($par * self::REORDER_FACTOR);

            foreach ($group as $stock) {
                if (! $overwrite && $stock->min_qty_threshold_base !== null) {
                    $skipped++;

                    continue;
                }

                $stock->min_qty_threshold_base = $threshold;
                $stock->save();
                $updated++;
            }
        }

        $low = ClinicItemStock::query()->withoutGlobalScopes()
            ->whereNotNull('min_qty_threshold_base')
            ->whereColumn('qty_on_hand_base', '<=', 'min_qty_threshold_base')
            ->count();

        $this->command?->info("Set re-order points on {$updated} stock rows (skipped {$skipped} that already had one).");
        $this->command?->info("{$low} of {$rows->count()} lines are at or below their re-order point.");
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        return $n % 2
            ? (float) $values[intdiv($n, 2)]
            : ((float) $values[$n / 2 - 1] + (float) $values[$n / 2]) / 2;
    }

    /** Half-unit steps read better on a shelf than 1.4 of a vial. */
    private function roundToHalf(float $value): float
    {
        return max(1.0, round($value * 2) / 2);
    }
}
