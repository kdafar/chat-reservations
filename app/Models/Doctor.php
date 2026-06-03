<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doctor extends Model
{
    protected $guarded = [];

    use SoftDeletes;
    use \App\Models\Concerns\BelongsToBranchScope;
    use \App\Models\Concerns\LogsClinicActivity;

    protected $activityLogName = 'doctors';

    protected $activityLogExcept = ['working_hours', 'updated_at'];

    protected $casts = [
        'working_hours' => 'array', // Automatically handles the JSON schedule
        'is_active' => 'boolean',
        'consultation_fee' => 'decimal:3',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function compensationProfile(): HasOne
    {
        return $this->hasOne(DoctorCompensationProfile::class);
    }

    public function compensationLedgers(): HasMany
    {
        return $this->hasMany(DoctorCompensationLedger::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shifts()
    {
        return $this->hasMany(DoctorShift::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    /**
     * Check if doctor is working on a specific date/time
     * Usage: $doctor->isWorking('2026-01-20', '10:00:00')
     */
    public function isWorking($date, $time): bool
    {
        return $this->shifts()
            ->where('shift_date', $date)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->where('is_cancelled', false)
            ->exists();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }
}
