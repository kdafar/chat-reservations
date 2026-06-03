<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitCharge extends Model
{
    use BelongsToBranchScope;
    use \App\Models\Concerns\LogsClinicActivity;

    protected $activityLogName = 'visit_charges';

    protected $guarded = [];

    protected $casts = [
        'visit_id' => 'integer',
        'branch_id' => 'integer',
        'qty' => 'decimal:3',
        'unit_price_snapshot' => 'decimal:3',
        'line_total' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'added_by_user_id' => 'integer',
    ];

    /**
     * Net line total after subtracting the per-line discount.
     * Visit-level discount_total is applied separately on top of this.
     */
    public function getNetTotalAttribute(): float
    {
        return max(0, (float) $this->line_total - (float) $this->discount_amount);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }
}
