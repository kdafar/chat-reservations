<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty_base' => 'decimal:4',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function clinicItem(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class, 'clinic_item_id');
    }
}
