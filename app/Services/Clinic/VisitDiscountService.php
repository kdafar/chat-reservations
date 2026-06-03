<?php

namespace App\Services\Clinic;

use App\Models\ClinicCoupon;
use App\Models\Visit;

/**
 * Resolves a visit's visit-level discount (KWD) from its inputs: a manual
 * amount/percent and/or an applied coupon, against the discountable subtotal
 * (fees + packages + items, each already net of per-line discounts).
 *
 * The resolved figure is written to visit.discount_total by VisitCostingService
 * so percent/coupon discounts recompute automatically as lines change.
 */
class VisitDiscountService
{
    /** Does the visit carry any visit-level discount input (vs a legacy goodwill discount_total)? */
    public function hasInputs(Visit $visit): bool
    {
        return in_array($visit->discount_type, ['amount', 'percent'], true)
            || $visit->coupon_id !== null;
    }

    public function resolve(Visit $visit, float $subtotal): float
    {
        $subtotal = max(0.0, $subtotal);

        $manual = 0.0;
        if ($visit->discount_type === 'percent') {
            $manual = $subtotal * ((float) $visit->discount_value / 100);
        } elseif ($visit->discount_type === 'amount') {
            $manual = (float) $visit->discount_value;
        }

        $coupon = 0.0;
        if ($visit->coupon_id) {
            $c = $visit->relationLoaded('coupon') ? $visit->coupon : ClinicCoupon::find($visit->coupon_id);
            if ($c) {
                $coupon = $c->discountFor($subtotal);
            }
        }

        return round(max(0.0, min($subtotal, $manual + $coupon)), 3);
    }
}
