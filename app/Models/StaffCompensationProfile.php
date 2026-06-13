<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-user salary structure — the non-doctor analog of
 * DoctorCompensationProfile. Holds basic salary, recurring allowances and
 * deductions, and the hire/termination dates that drive end-of-service
 * gratuity. A doctor on a base-salary + commission deal can have one too.
 */
class StaffCompensationProfile extends Model
{
    use SoftDeletes;
    use Concerns\BelongsToBranchScope;
    use Concerns\LogsClinicActivity;

    protected $guarded = [];

    protected $activityLogName = 'staff_compensation_profiles';

    protected $casts = [
        'basic_salary' => 'decimal:3',
        'allowances' => 'array',
        'deductions' => 'array',
        'annual_leave_days' => 'integer',
        'hire_date' => 'date:Y-m-d',
        'termination_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'user_id' => 'integer',
        'doctor_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            // Auto-link doctor_id when the user IS a doctor (mirror StaffLeave).
            if (! empty($row->user_id) && empty($row->doctor_id)) {
                $row->doctor_id = Doctor::query()->where('user_id', $row->user_id)->value('id');
            }
        });
    }

    /** Sum of the recurring allowance lines. */
    public function allowancesTotal(): float
    {
        return collect($this->allowances ?? [])->sum(fn ($a) => (float) ($a['amount'] ?? 0));
    }

    /** Sum of the recurring deduction lines. */
    public function deductionsTotal(): float
    {
        return collect($this->deductions ?? [])->sum(fn ($d) => (float) ($d['amount'] ?? 0));
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
}
