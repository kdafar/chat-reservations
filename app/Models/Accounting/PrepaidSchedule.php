<?php

namespace App\Models\Accounting;

use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prepaid expense amortised straight-line over its term. Each month the
 * amortization run releases one slice from the prepaid asset to expense.
 */
class PrepaidSchedule extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'total_amount' => 'decimal:3',
        'amortized_amount' => 'decimal:3',
        'term_months' => 'integer',
        'start_date' => 'date',
        'last_amortized_on' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function prepaidAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'prepaid_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function amortizations(): HasMany
    {
        return $this->hasMany(PrepaidAmortization::class);
    }

    /** Straight-line monthly slice (last month absorbs rounding). */
    public function monthlySlice(): float
    {
        if ($this->term_months <= 0) {
            return 0.0;
        }

        return round((float) $this->total_amount / $this->term_months, 3);
    }

    /** Amount to release for the month ending $monthEnd (clamped to remaining). */
    public function sliceForMonth(Carbon $monthEnd): float
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }
        if ($this->start_date && $this->start_date->copy()->startOfMonth()->greaterThan($monthEnd)) {
            return 0.0; // hasn't started
        }
        $remaining = round((float) $this->total_amount - (float) $this->amortized_amount, 3);
        if ($remaining <= 0) {
            return 0.0;
        }

        return min($this->monthlySlice(), $remaining);
    }
}
