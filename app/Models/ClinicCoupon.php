<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A clinic-side coupon applied to a visit at checkout. The resolved discount is
 * written to the visit (visit.discount_total via VisitDiscountService).
 */
class ClinicCoupon extends Model
{
    protected $guarded = [];

    protected $casts = [
        'discount_value' => 'decimal:3',
        'min_subtotal' => 'decimal:3',
        'max_discount' => 'decimal:3',
        'branch_id' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'is_active' => 'boolean',
        'stacks_with_promotions' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Why the coupon cannot be used right now, or null when it is redeemable.
     */
    public function rejectionReason(float $subtotal, ?int $branchId = null): ?string
    {
        $today = now()->startOfDay();

        if (! $this->is_active) {
            return 'This coupon is not active.';
        }
        if ($this->starts_at && $today->lt($this->starts_at->startOfDay())) {
            return 'This coupon is not valid yet.';
        }
        if ($this->ends_at && $today->gt($this->ends_at->startOfDay())) {
            return 'This coupon has expired.';
        }
        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return 'This coupon has reached its usage limit.';
        }
        if ($this->branch_id !== null && $branchId !== null && (int) $this->branch_id !== (int) $branchId) {
            return 'This coupon is not valid at this branch.';
        }
        if ($subtotal + 0.0001 < (float) $this->min_subtotal) {
            return 'Minimum spend of '.number_format((float) $this->min_subtotal, 3).' KWD not met.';
        }

        return null;
    }

    public function isRedeemable(float $subtotal, ?int $branchId = null): bool
    {
        return $this->rejectionReason($subtotal, $branchId) === null;
    }

    /**
     * Discount this coupon yields for a given visit subtotal (KWD), capped at
     * max_discount (percent coupons) and never exceeding the subtotal.
     */
    public function discountFor(float $subtotal): float
    {
        $raw = $this->discount_type === 'percent'
            ? $subtotal * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        if ($this->discount_type === 'percent' && $this->max_discount !== null) {
            $raw = min($raw, (float) $this->max_discount);
        }

        return round(max(0.0, min($raw, $subtotal)), 3);
    }
}
