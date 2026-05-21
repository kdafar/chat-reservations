<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitStockRequestLine extends Model
{
    // Lines do not have branch_id column, so DO NOT use BelongsToBranchScope here
    // (unless your trait supports scoping through parent relation).
    protected $guarded = [];

    protected $casts = [
        'visit_stock_request_id' => 'integer',
        'clinic_item_id' => 'integer',
        'qty_base' => 'decimal:4',
        'unit_cost_snapshot' => 'decimal:3',
        'unit_price_snapshot' => 'decimal:3',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(VisitStockRequest::class, 'visit_stock_request_id');
    }

    public function clinicItem(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class, 'clinic_item_id');
    }
}
