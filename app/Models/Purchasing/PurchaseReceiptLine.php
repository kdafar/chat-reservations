<?php

namespace App\Models\Purchasing;

use App\Models\ClinicItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item line within a goods-received note.
 */
class PurchaseReceiptLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'purchase_receipt_id' => 'integer',
        'purchase_order_line_id' => 'integer',
        'clinic_item_id' => 'integer',
        'qty_received' => 'decimal:4',
        'unit_cost' => 'decimal:3',
        'line_total' => 'decimal:3',
        'clinic_stock_movement_id' => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function clinicItem(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class);
    }
}
