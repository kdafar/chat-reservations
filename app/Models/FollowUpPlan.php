<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpPlan extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'suggested_at' => 'datetime',
        'auto_create_booking' => 'boolean',
        'patient_id' => 'integer',
        'doctor_id' => 'integer',
        'branch_id' => 'integer',
        'source_visit_id' => 'integer',
        'booking_id' => 'integer',
    ];

    public function sourceVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'source_visit_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
