<?php

namespace App\Services\Clinic;

use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class VisitCostingService
{
    public const VERSION = 'v2';

    private function r3(float $v): float
    {
        return round($v, 3);
    }

    public function compute(Visit $visit, ?int $actorUserId = null): Visit
    {
        if (! config('clinic.visit_financials_enabled', false)) {
            return $visit;
        }

        // Net totals — line-level discount_amount is subtracted as charges/items are summed.
        // Visit.discount_total is an additional manual override applied on top (goodwill).
        return DB::transaction(function () use ($visit, $actorUserId) {
            $items = $visit->visitItems()
                ->get(['id', 'qty', 'unit_cost_snapshot', 'unit_price_snapshot', 'line_cost_total', 'line_price_total', 'discount_amount']);

            $itemsCostTotal = 0.0;
            $itemsPriceTotal = 0.0;

            foreach ($items as $it) {
                $qty = (float) ($it->qty ?? 0);
                $unitCost = (float) ($it->unit_cost_snapshot ?? 0);
                $unitPrice = (float) ($it->unit_price_snapshot ?? 0);
                $lineDiscount = (float) ($it->discount_amount ?? 0);

                $lineCost = $this->r3($qty * $unitCost);
                $linePrice = $this->r3($qty * $unitPrice);
                // Net price = gross line price minus the per-line discount (clamped at 0).
                $netLinePrice = $this->r3(max(0.0, $linePrice - $lineDiscount));

                $itemsCostTotal += $lineCost;
                $itemsPriceTotal += $netLinePrice;

                $dbLineCost = $this->r3((float) ($it->line_cost_total ?? 0));
                $dbLinePrice = $this->r3((float) ($it->line_price_total ?? 0));

                if ($dbLineCost !== $lineCost || $dbLinePrice !== $linePrice) {
                    $it->forceFill([
                        'line_cost_total' => $lineCost,
                        'line_price_total' => $linePrice,
                    ])->save();
                }
            }

            $itemsCostTotal = $this->r3($itemsCostTotal);
            $itemsPriceTotal = $this->r3($itemsPriceTotal);

            // Fees = sum of every VisitCharge row on the visit, net of each charge's discount_amount.
            // The consultation fee is already stored as a VisitCharge at collection time,
            // so we no longer need to look up the doctor's current consultation_fee
            // (which would corrupt the historical snapshot when the doctor's fee changes).
            $feesTotal = $this->r3(
                (float) $visit->visitCharges()
                    ->selectRaw('COALESCE(SUM(CASE WHEN line_total - discount_amount > 0 THEN line_total - discount_amount ELSE 0 END), 0) as total')
                    ->value('total')
            );

            // Packages net of each package line's discount_amount.
            $packagesPriceTotal = $this->r3(
                (float) $visit->visitPackages()
                    ->selectRaw('COALESCE(SUM(CASE WHEN line_total - discount_amount > 0 THEN line_total - discount_amount ELSE 0 END), 0) as total')
                    ->value('total')
            );

            // Visit-level discount: resolve from inputs (manual amount/percent +
            // coupon) against the subtotal when present; otherwise keep whatever
            // discount_total was set directly (legacy goodwill / Filament).
            $subtotal = $feesTotal + $packagesPriceTotal + $itemsPriceTotal;
            $discountSvc = app(\App\Services\Clinic\VisitDiscountService::class);
            $discount = $discountSvc->hasInputs($visit)
                ? $discountSvc->resolve($visit, $subtotal)
                : $this->r3((float) ($visit->discount_total ?? 0));

            $profit = $this->r3(
                ($feesTotal + $packagesPriceTotal + $itemsPriceTotal)
                - $discount
                - $itemsCostTotal
            );

            $visit->forceFill([
                'fees_total' => $feesTotal,
                'packages_price_total' => $packagesPriceTotal,
                'items_cost_total' => $itemsCostTotal,
                'items_price_total' => $itemsPriceTotal,
                'discount_total' => $discount,
                'profit_total' => $profit,
                'computed_at' => now(),
                'computed_version' => self::VERSION,
            ])->save();

            if (config('clinic.doctor_comp_enabled', false)
                && class_exists(\App\Services\Clinic\DoctorCompensationService::class)
            ) {
                app(\App\Services\Clinic\DoctorCompensationService::class)->sync($visit, $actorUserId);
            }

            return $visit->refresh();
        });
    }

    /**
     * Remaining balance for payment forms.
     * Total due = fees + packages + items_price − discount.
     */
    public function getRemainingBalance(Visit $visit): float
    {
        $this->compute($visit);

        $totalDue = (float) ($visit->fees_total ?? 0)
            + (float) ($visit->packages_price_total ?? 0)
            + (float) ($visit->items_price_total ?? 0)
            - (float) ($visit->discount_total ?? 0);

        $paidSoFar = (float) $visit->payments()
            ->where('status', 'paid')
            ->sum('amount');

        $balance = $totalDue - $paidSoFar;

        return $balance > 0 ? $balance : 0.0;
    }
}
