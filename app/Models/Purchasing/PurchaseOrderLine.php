<?php

namespace App\Models\Purchasing;

use App\Models\ClinicItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered line on a purchase order. qty_received tracks partial receiving.
 */
class PurchaseOrderLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'clinic_item_id' => 'integer',
        'qty_ordered' => 'decimal:4',
        'qty_received' => 'decimal:4',
        'unit_cost' => 'decimal:3',
        'discount_value' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'line_total' => 'decimal:3',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function clinicItem(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class);
    }

    /** Quantity still to be received on this line. */
    public function qtyRemaining(): float
    {
        return max(0, round((float) $this->qty_ordered - (float) $this->qty_received, 4));
    }

    /** Discount on the full ordered line, in the PO currency. */
    public function discountAmount(): float
    {
        $gross = (float) $this->qty_ordered * (float) $this->unit_cost;

        return self::computeDiscount($gross, (string) $this->discount_type, (float) $this->discount_value);
    }

    /** Effective per-unit cost after the line discount (PO currency). */
    public function netUnitCost(): float
    {
        $qty = (float) $this->qty_ordered;
        if ($qty <= 0) {
            return (float) $this->unit_cost;
        }

        return round(((float) $this->line_total) / $qty, 6);
    }

    /** Discount amount for a gross value given a type + value. */
    public static function computeDiscount(float $gross, string $type, float $value): float
    {
        if ($value <= 0 || $gross <= 0) {
            return 0.0;
        }
        $d = $type === 'amount' ? $value : $gross * ($value / 100);

        return round(min($d, $gross), 3);
    }
}
