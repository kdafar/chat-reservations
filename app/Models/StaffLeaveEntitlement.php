<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Annual leave allowance for a user in a given year. used-days are NOT stored
 * here — they're computed from approved staff_leaves of the same type/year by
 * LeaveBalanceService, so the balance can never drift out of sync.
 */
class StaffLeaveEntitlement extends Model
{
    use LogsClinicActivity;

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'entitled_days' => 'decimal:2',
        'carried_over_days' => 'decimal:2',
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
