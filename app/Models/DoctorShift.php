<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorShift extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    // 1. Strict Fillables (Security)
    protected $fillable = [
        'doctor_id',
        'branch_id',
        'shift_date',
        'start_time',
        'end_time',
        'break_minutes',
        'is_cancelled',
    ];

    // 2. Strong Casting (Prevents "Call to member function format() on string" errors)
    protected $casts = [
        'shift_date' => 'date:Y-m-d',
        'is_cancelled' => 'boolean',
        'break_minutes' => 'integer',
        // Note: Time columns usually return as strings (H:i:s) in Laravel unless custom cast used.
        // We will leave them as strings to avoid carbon timezone complexities in legacy views.
    ];

    // 3. Relationships
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // 4. Helper for Reports (The "Utilization" Math)
    public function getDurationHoursAttribute(): float
    {
        // Ugly but Safe Manual Calculation
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        $diffInMinutes = $end->diffInMinutes($start) - $this->break_minutes;

        return max(0, $diffInMinutes / 60); // Never return negative hours
    }
}
