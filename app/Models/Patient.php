<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $guarded = [];

    protected $casts = [
        'dob' => 'date',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    // Connects to your existing Booking system
    public function bookings(): HasMany
    {
        // Assuming your Booking model is in App\Models\Booking
        return $this->hasMany(Booking::class, 'msisdn', 'phone');
        // Note: Ideally, future Bookings should have a patient_id column.
        // For now, we might link via phone if patient_id isn't on bookings table yet.
        // If you updated bookings table to have patient_id, change to:
        // return $this->hasMany(Booking::class);
    }
}
