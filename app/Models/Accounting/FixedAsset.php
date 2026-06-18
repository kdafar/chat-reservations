<?php

namespace App\Models\Accounting;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A capitalised fixed asset. Depreciates straight-line over useful_life_months
 * from in_service_date down to salvage_value. The depreciation run (see
 * DepreciationService) posts one balanced entry per asset per month.
 */
class FixedAsset extends Model
{
    use LogsClinicActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';

    public const STATUS_DISPOSED = 'disposed';

    protected $guarded = [];

    protected $casts = [
        'cost' => 'decimal:3',
        'salvage_value' => 'decimal:3',
        'accumulated_depreciation' => 'decimal:3',
        'disposal_proceeds' => 'decimal:3',
        'useful_life_months' => 'integer',
        'in_service_date' => 'date',
        'last_depreciated_on' => 'date',
        'disposed_on' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class);
    }

    /** Total amount that will be depreciated over the asset's life. */
    public function depreciableBase(): float
    {
        return max(0.0, round((float) $this->cost - (float) $this->salvage_value, 3));
    }

    /** Net book value = cost − accumulated depreciation. */
    public function netBookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 3);
    }

    /** Straight-line charge for one full month (last month absorbs rounding). */
    public function monthlyCharge(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        return round($this->depreciableBase() / $this->useful_life_months, 3);
    }

    /**
     * Depreciation still owed as of a month-end: clamps so accumulated never
     * exceeds the depreciable base (the final month picks up the rounding tail).
     */
    public function chargeForMonth(Carbon $monthEnd): float
    {
        if ($this->status === self::STATUS_DISPOSED) {
            return 0.0;
        }
        // Not in service yet for this month.
        if ($this->in_service_date && $this->in_service_date->copy()->startOfMonth()->greaterThan($monthEnd)) {
            return 0.0;
        }
        $remaining = round($this->depreciableBase() - (float) $this->accumulated_depreciation, 3);
        if ($remaining <= 0) {
            return 0.0;
        }

        return min($this->monthlyCharge(), $remaining);
    }
}
