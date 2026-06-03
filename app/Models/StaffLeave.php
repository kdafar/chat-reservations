<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generic leave request — any User (admin, receptionist, doctor, lab tech)
 * can request time off. `doctor_id` is an optional convenience column so
 * the per-doctor relation manager can filter cleanly without a User join.
 */
class StaffLeave extends Model
{
    use SoftDeletes;
    use Concerns\BelongsToBranchScope;
    use Concerns\LogsClinicActivity;

    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending',
        'type' => 'annual',
    ];

    protected $activityLogName = 'staff_leaves';

    public const TYPE_ANNUAL = 'annual';
    public const TYPE_SICK = 'sick';
    public const TYPE_MATERNITY = 'maternity';
    public const TYPE_UNPAID = 'unpaid';
    public const TYPE_EMERGENCY = 'emergency';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'days_count' => 'integer',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if ($row->starts_on && $row->ends_on) {
                $row->days_count = max(1, $row->starts_on->diffInDays($row->ends_on) + 1);
            }
            // Auto-link doctor_id from user_id when the user IS a doctor.
            if (! empty($row->user_id) && empty($row->doctor_id)) {
                $doctorId = Doctor::query()->where('user_id', $row->user_id)->value('id');
                if ($doctorId) {
                    $row->doctor_id = $doctorId;
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
