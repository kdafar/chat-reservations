<?php

namespace App\Models\Inpatient;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionRound extends Model
{
    use LogsClinicActivity;

    protected $guarded = [];

    protected $casts = [
        'round_date' => 'date',
        'vitals' => 'array',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
