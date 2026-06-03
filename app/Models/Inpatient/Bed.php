<?php

namespace App\Models\Inpatient;

use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends Model
{
    use BelongsToBranchScope;

    protected $guarded = [];

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_CLEANING = 'cleaning';

    protected $casts = [
        'daily_rate_override' => 'decimal:3',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(AdmissionBedStay::class);
    }

    public function currentStay(): HasOne
    {
        return $this->hasOne(AdmissionBedStay::class)->whereNull('released_at')->latestOfMany('id');
    }

    /**
     * Effective daily rate: bed override falls back to ward default.
     * Snapshot this at assignment time — don't recompute mid-stay.
     */
    public function effectiveDailyRate(): float
    {
        return (float) ($this->daily_rate_override ?? $this->ward?->daily_rate ?? 0);
    }
}
