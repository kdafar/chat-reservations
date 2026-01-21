<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitStockRequest extends Model
{
    use BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'visit_id' => 'integer',
        'branch_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'fulfilled_by_user_id' => 'integer',
        'fulfilled_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VisitStockRequestLine::class, 'visit_stock_request_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }
}
