<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorCompensationProfile extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'percentage_rate' => 'decimal:3',
        'is_active' => 'boolean',
        'doctor_id' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
