<?php

namespace App\Models\Insurance;

use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InsurancePreauthorization extends Model
{
    use BelongsToBranchScope;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'insurance_preauthorizations';

    protected $fillable = [
        'patient_policy_id',
        'visit_id',
        'branch_id',
        'requested_by_user_id',
        'services',
        'estimated_total',
        'requested_at',
        'reference_no',
        'status',
        'approved_amount',
        'approval_letter_path',
        'valid_from',
        'valid_until',
        'decision_notes',
        'decided_at',
        'decided_by_user_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'services' => 'array',
        'estimated_total' => 'decimal:3',
        'approved_amount' => 'decimal:3',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'valid_from' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
    ];

    public function patientPolicy(): BelongsTo
    {
        return $this->belongsTo(PatientInsurancePolicy::class, 'patient_policy_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function claim(): HasOne
    {
        return $this->hasOne(InsuranceClaim::class, 'preauth_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'decided_by_user_id');
    }
}
