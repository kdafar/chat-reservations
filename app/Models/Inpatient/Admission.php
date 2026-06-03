<?php

namespace App\Models\Inpatient;

use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Admission extends Model
{
    use BelongsToBranchScope;
    use \App\Models\Concerns\LogsClinicActivity;

    protected $guarded = [];

    protected $activityLogName = 'admissions';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISCHARGED = 'discharged';
    public const STATUS_TRANSFERRED_OUT = 'transferred_out'; // moved to another facility
    public const STATUS_LAMA = 'lama';                       // left against medical advice
    public const STATUS_EXPIRED = 'expired';

    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
        'expected_discharge_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function admittingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'admitting_doctor_id');
    }

    public function admittingVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'admitting_visit_id');
    }

    public function finalVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'final_visit_id');
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by_user_id');
    }

    public function bedStays(): HasMany
    {
        return $this->hasMany(AdmissionBedStay::class);
    }

    public function currentBedStay(): HasOne
    {
        return $this->hasOne(AdmissionBedStay::class)->whereNull('released_at')->latestOfMany('id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(AdmissionCharge::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(AdmissionRound::class)->orderByDesc('round_date');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
