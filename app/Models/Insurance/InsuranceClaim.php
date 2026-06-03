<?php

namespace App\Models\Insurance;

use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceClaim extends Model
{
    use BelongsToBranchScope;
    use \App\Models\Concerns\LogsClinicActivity;

    protected $activityLogName = 'insurance_claims';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $table = 'insurance_claims';

    protected $fillable = [
        'visit_id',
        'patient_policy_id',
        'preauth_id',
        'branch_id',
        'claim_number',
        'submitted_by_user_id',
        'submitted_at',
        'total_charged',
        'patient_copay',
        'insurer_payable',
        'approved_amount',
        'rejected_amount',
        'paid_amount',
        'write_off_amount',
        'status',
        'decision_notes',
        'decided_at',
        'paid_at',
        'eob_path',
        'reference_no',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'total_charged' => 'decimal:3',
        'patient_copay' => 'decimal:3',
        'insurer_payable' => 'decimal:3',
        'approved_amount' => 'decimal:3',
        'rejected_amount' => 'decimal:3',
        'paid_amount' => 'decimal:3',
        'write_off_amount' => 'decimal:3',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function patientPolicy(): BelongsTo
    {
        return $this->belongsTo(PatientInsurancePolicy::class, 'patient_policy_id');
    }

    public function preauthorization(): BelongsTo
    {
        return $this->belongsTo(InsurancePreauthorization::class, 'preauth_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InsuranceClaimItem::class, 'claim_id');
    }

    public function stateLogs(): HasMany
    {
        return $this->hasMany(InsuranceClaimStateLog::class, 'claim_id')
            ->orderBy('changed_at', 'asc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InsuranceClaimPayment::class, 'claim_id');
    }

    public function balanceDue(): float
    {
        return (float) $this->insurer_payable
            - (float) $this->paid_amount
            - (float) $this->write_off_amount
            - (float) $this->rejected_amount;
    }
}
