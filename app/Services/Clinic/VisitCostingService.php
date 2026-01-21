<?php

namespace App\Services\Clinic;

use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class VisitCostingService
{
    public const VERSION = 'v1';

    private function r3(float $v): float
    {
        return round($v, 3);
    }

    public function compute(Visit $visit, ?int $actorUserId = null): Visit
    {
        if (! config('clinic.visit_financials_enabled', false)) {
            return $visit;
        }

        return DB::transaction(function () use ($visit, $actorUserId) {
            // Note: Assuming 'visitItems' is the correct relationship method name in your system.
            $items = $visit->visitItems()
                ->get(['id', 'qty', 'unit_cost_snapshot', 'unit_price_snapshot', 'line_cost_total', 'line_price_total']);

            $itemsCostTotal = 0.0;
            $itemsPriceTotal = 0.0;

            foreach ($items as $it) {
                $qty = (float) ($it->qty ?? 0);
                $unitCost = (float) ($it->unit_cost_snapshot ?? 0);
                $unitPrice = (float) ($it->unit_price_snapshot ?? 0);

                $lineCost = $this->r3($qty * $unitCost);
                $linePrice = $this->r3($qty * $unitPrice);

                $itemsCostTotal += $lineCost;
                $itemsPriceTotal += $linePrice;

                // compare with rounding to avoid float jitter
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

            // ---------------------------------------------------------
            // FIX: Fetch Fee from Doctor (Smart Calculation)
            // ---------------------------------------------------------
            // We must ensure the doctor relation is loaded to get the master fee.
            if (! $visit->relationLoaded('doctor')) {
                $visit->load('doctor');
            }

            // If the visit has a doctor, pull their consultation fee.
            // Otherwise fallback to existing fees_total or 0.
            $doctorFee = (float) ($visit->doctor->consultation_fee ?? $visit->fees_total ?? 0);
            $fees = $this->r3($doctorFee);

            $discount = $this->r3((float) ($visit->discount_total ?? 0));

            // FIXED: Added itemsPriceTotal (Revenue) to the profit calculation.
            // Previous logic was: Fees - Discount - Cost (which missed the item sales revenue).
            // New logic: (Fees + Item Sales) - Discount - Item Costs
            $profit = $this->r3(($fees + $itemsPriceTotal) - $discount - $itemsCostTotal);

            $visit->forceFill([
                'fees_total' => $fees, // <--- IMPORTANT: Save the fetched fee back to DB
                'items_cost_total' => $itemsCostTotal,
                'items_price_total' => $itemsPriceTotal,
                'profit_total' => $profit,
                'computed_at' => now(),
                'computed_version' => self::VERSION,
            ])->save();

            if (config('clinic.doctor_comp_enabled', false)) {
                // Defensive check: Ensure the service exists before calling
                if (class_exists(\App\Services\Clinic\DoctorCompensationService::class)) {
                    app(\App\Services\Clinic\DoctorCompensationService::class)->sync($visit, $actorUserId);
                }
            }

            return $visit->refresh();
        });
    }

    /**
     * Helper to get the remaining balance due for a visit.
     * used by the Payment Form.
     * * PRESERVED FOR COMPATIBILITY with VisitPaymentsRelationManager
     */
    public function getRemainingBalance(Visit $visit): float
    {
        // Ensure we have the latest totals
        $this->compute($visit);

        $totalDue = ($visit->fees_total ?? 0) + ($visit->items_price_total ?? 0);

        // Sum only valid payments (paid)
        // Defensive check on payments relationship
        $paidSoFar = $visit->payments
            ? $visit->payments->where('status', 'paid')->sum('amount')
            : 0;

        $balance = $totalDue - $paidSoFar;

        // Never return negative balance for a payment form default
        return $balance > 0 ? (float) $balance : 0.00;
    }
}
