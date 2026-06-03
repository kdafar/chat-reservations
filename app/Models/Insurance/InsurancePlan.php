<?php

namespace App\Models\Insurance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsurancePlan extends Model
{
    protected $table = 'insurance_plans';

    protected $fillable = [
        'insurer_id',
        'name',
        'name_ar',
        'code',
        'tier',
        'effective_from',
        'effective_until',
        'is_active',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'bool',
        'effective_from' => 'date:Y-m-d',
        'effective_until' => 'date:Y-m-d',
    ];

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class, 'insurer_id');
    }

    public function coverageRules(): HasMany
    {
        return $this->hasMany(InsuranceCoverageRule::class, 'plan_id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(PatientInsurancePolicy::class, 'plan_id');
    }
}
