<?php

namespace Database\Seeders\Demo;

use App\Models\ClinicItem;
use App\Models\Visit;
use App\Services\Clinic\VisitCostingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives treatments a cost of delivery, so the profit figures mean something.
 *
 * Two problems this fixes:
 *
 *  1. Every one of the 84 billable services carries default_cost = 0, so no
 *     visit has ever had a cost of goods against it.
 *  2. The generated treatments were written as visit_charges — a free-text
 *     line with a price and no cost column at all.
 *
 * Together those made profit_total equal revenue, and Clinic Reports headlined
 * a 95% margin. This maps each treatment charge onto the catalogue service
 * nearest its price, re-books it as a visit_item carrying both price and cost,
 * and re-costs the visit. Revenue is unchanged — the value simply moves from
 * fees_total into items_price_total, and items_cost_total stops being zero.
 *
 * Note this only affects the visit-level margin shown on the clinic reports.
 * The general ledger takes its COGS from stock consumption, not from here, so
 * the P&L is untouched.
 */
class DemoTreatmentCostingSeeder extends Seeder
{
    /** Consumable + product cost as a share of list price, per treatment band. */
    protected function costRatioFor(float $price, int $itemId): float
    {
        // Injectables (toxin, filler) carry the highest product cost; device-based
        // work is mostly capacity, so its marginal cost is much lower.
        $base = match (true) {
            $price >= 120 => 0.34,
            $price >= 60 => 0.29,
            $price >= 30 => 0.24,
            default => 0.18,
        };

        return round($base + ((($itemId % 9) - 4) * 0.012), 4);
    }

    public function run(): void
    {
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $services = ClinicItem::query()->withoutGlobalScopes()
            ->where('type', 'service')->where('is_billable', true)
            ->orderBy('default_price')
            ->get(['id', 'default_price', 'default_cost']);

        if ($services->isEmpty()) {
            $this->command?->warn('DemoTreatmentCostingSeeder: no billable services.');

            return;
        }

        $this->backfillCatalogueCosts($services);

        $converted = $this->convertCharges($services);
        $this->command?->info("DemoTreatmentCostingSeeder: re-booked {$converted} treatment lines as costed items.");
    }

    /** Put a standing cost on the catalogue so newly-added items behave too. */
    protected function backfillCatalogueCosts($services): void
    {
        $updated = 0;
        foreach ($services as $item) {
            if ((float) $item->default_cost > 0) {
                continue;
            }
            $price = (float) $item->default_price;
            if ($price <= 0) {
                continue;
            }
            DB::table('clinic_items')->where('id', $item->id)->update([
                'default_cost' => round($price * $this->costRatioFor($price, $item->id), 3),
            ]);
            $updated++;
        }

        $this->command?->info("DemoTreatmentCostingSeeder: priced cost onto {$updated} catalogue services.");
    }

    /**
     * Move every treatment charge onto a catalogue item. The consultation fee
     * stays a charge — it is the doctor's time, not a product.
     */
    protected function convertCharges($services): int
    {
        $costing = app(VisitCostingService::class);

        // Nearest-price lookup, so a 145 KWD toxin charge maps to a comparably
        // priced catalogue service rather than an arbitrary one.
        $byPrice = $services->map(fn ($s) => [
            'id' => $s->id,
            'price' => (float) $s->default_price,
            'ratio' => $this->costRatioFor((float) $s->default_price, $s->id),
        ])->sortBy('price')->values();

        $match = function (float $price) use ($byPrice) {
            $best = null;
            $bestGap = INF;
            foreach ($byPrice as $cand) {
                $gap = abs($cand['price'] - $price);
                if ($gap < $bestGap) {
                    $bestGap = $gap;
                    $best = $cand;
                }
            }

            return $best;
        };

        $converted = 0;
        $touched = 0;

        Visit::query()->withoutGlobalScopes()
            ->where('status', Visit::STATUS_COMPLETED)
            ->whereNotNull('computed_at')
            ->orderBy('id')
            ->chunkById(200, function ($visits) use (&$converted, &$touched, $match, $costing) {
                foreach ($visits as $visit) {
                    $charges = DB::table('visit_charges')
                        ->where('visit_id', $visit->id)
                        ->where('label', '!=', 'Consultation Fee')
                        ->get();
                    if ($charges->isEmpty()) {
                        continue;
                    }

                    // visit_items is unique on (visit_id, clinic_item_id), and two
                    // charges can land on the same catalogue service — fold them
                    // into a single line with the combined quantity.
                    $agg = [];
                    foreach ($charges as $charge) {
                        $qty = (float) $charge->qty ?: 1;
                        $unitPrice = (float) $charge->unit_price_snapshot;
                        $cand = $match($unitPrice);
                        $unitCost = round($unitPrice * $cand['ratio'], 3);
                        $id = $cand['id'];

                        if (! isset($agg[$id])) {
                            $agg[$id] = [
                                'visit_id' => $visit->id,
                                'clinic_item_id' => $id,
                                'branch_id' => $charge->branch_id,
                                'qty' => 0.0,
                                'unit_cost_snapshot' => $unitCost,
                                'unit_price_snapshot' => $unitPrice,
                                'line_cost_total' => 0.0,
                                'line_price_total' => 0.0,
                                'discount_amount' => 0.0,
                                'created_at' => $charge->created_at,
                                'updated_at' => $charge->updated_at,
                            ];
                        }
                        $agg[$id]['qty'] += $qty;
                        $agg[$id]['line_cost_total'] = round($agg[$id]['line_cost_total'] + ($unitCost * $qty), 3);
                        $agg[$id]['line_price_total'] = round($agg[$id]['line_price_total'] + ($unitPrice * $qty), 3);
                        $agg[$id]['discount_amount'] = round($agg[$id]['discount_amount'] + (float) $charge->discount_amount, 3);
                        $converted++;
                    }

                    // The visit may already carry that item from the original data.
                    $existing = DB::table('visit_items')->where('visit_id', $visit->id)
                        ->pluck('id', 'clinic_item_id')->all();

                    DB::transaction(function () use ($agg, $existing, $charges) {
                        $insert = [];
                        foreach ($agg as $itemId => $row) {
                            if (isset($existing[$itemId])) {
                                DB::table('visit_items')->where('id', $existing[$itemId])->update([
                                    'qty' => DB::raw('qty + '.(float) $row['qty']),
                                    'line_cost_total' => DB::raw('line_cost_total + '.(float) $row['line_cost_total']),
                                    'line_price_total' => DB::raw('line_price_total + '.(float) $row['line_price_total']),
                                ]);

                                continue;
                            }
                            $insert[] = $row;
                        }
                        if ($insert) {
                            DB::table('visit_items')->insert($insert);
                        }
                        DB::table('visit_charges')->whereIn('id', $charges->pluck('id'))->delete();
                    });

                    // Re-cost on the visit's own date so computed_at doesn't jump to today.
                    Carbon::setTestNow(Carbon::parse($visit->computed_at));
                    $costing->compute($visit->refresh(), 0);
                    Carbon::setTestNow();

                    $touched++;
                    if ($touched % 500 === 0) {
                        $this->command?->info("  {$touched} visits re-costed");
                    }
                }
            });

        Carbon::setTestNow();

        return $converted;
    }
}
