<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generic attendance row — any User clocks in/out, doctors included.
 * `doctor_id` auto-populates from `user_id` on save when the user has a
 * linked Doctor record (so the per-doctor relation manager keeps working).
 */
class StaffAttendance extends Model
{
    use SoftDeletes;
    use Concerns\BelongsToBranchScope;
    use Concerns\LogsClinicActivity;

    protected $table = 'staff_attendances';

    protected $guarded = [];

    protected $activityLogName = 'staff_attendance';

    protected $casts = [
        // Plain calendar day — serialize as Y-m-d (no time/TZ) so it shows as
        // "2026-06-01" instead of an ISO datetime that shifts across midnight.
        'work_date' => 'date:Y-m-d',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'hours_worked' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if ($row->clock_in_at && $row->clock_out_at) {
                $hours = $row->clock_in_at->floatDiffInHours($row->clock_out_at);
                $row->hours_worked = round(max(0.0, $hours), 2);
            } else {
                $row->hours_worked = 0.0;
            }
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
