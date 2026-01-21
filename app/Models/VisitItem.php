<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitItem extends Model
{
    protected $guarded = [];

    use \App\Models\Concerns\BelongsToBranchScope;

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_cost_snapshot' => 'decimal:3',
        'unit_price_snapshot' => 'decimal:3',
        'line_cost_total' => 'decimal:3',
        'line_price_total' => 'decimal:3',
        'branch_id' => 'integer',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function clinicItem(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
