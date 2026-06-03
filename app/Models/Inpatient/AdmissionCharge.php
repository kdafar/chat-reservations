<?php

namespace App\Models\Inpatient;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCharge extends Model
{
    protected $guarded = [];

    public const SOURCE_BED_DAY = 'bed_day';
    public const SOURCE_MANUAL = 'manual';

    protected $casts = [
        'charge_date' => 'date',
        'amount' => 'decimal:3',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function bedStay(): BelongsTo
    {
        return $this->belongsTo(AdmissionBedStay::class, 'bed_stay_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
