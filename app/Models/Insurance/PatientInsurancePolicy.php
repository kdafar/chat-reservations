<?php

namespace App\Models\Insurance;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientInsurancePolicy extends Model
{
    use \App\Models\Concerns\LogsClinicActivity;

    protected $activityLogName = 'insurance_policies';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    public const REL_SELF = 'self';

    public const REL_SPOUSE = 'spouse';

    public const REL_CHILD = 'child';

    public const REL_PARENT = 'parent';

    public const REL_OTHER = 'other';

    protected $table = 'patient_insurance_policies';

    protected $fillable = [
        'patient_id',
        'insurer_id',
        'plan_id',
        'policy_number',
        'member_id',
        'card_number',
        'holder_name',
        'holder_relationship',
        'status',
        'is_primary',
        'priority',
        'effective_from',
        'effective_until',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_primary' => 'bool',
        'effective_from' => 'date:Y-m-d',
        'effective_until' => 'date:Y-m-d',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class, 'insurer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'plan_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class, 'patient_policy_id');
    }

    public function preauthorizations(): HasMany
    {
        return $this->hasMany(InsurancePreauthorization::class, 'patient_policy_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        $today = now()->toDateString();

        return $q->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $w) use ($today) {
                $w->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            });
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->effective_from && $this->effective_from->greaterThan($today)) {
            return false;
        }

        if ($this->effective_until && $this->effective_until->lessThan($today)) {
            return false;
        }

        return true;
    }
}
