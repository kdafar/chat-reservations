<?php

namespace App\Models\Insurance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceCoverageRule extends Model
{
    public const KIND_CONSULTATION = 'consultation';

    public const KIND_SERVICES = 'services';

    public const KIND_MEDICINES = 'medicines';

    public const KIND_OTHER = 'other';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_COPAY_AMOUNT = 'copay_amount';

    protected $table = 'insurance_coverage_rules';

    protected $fillable = [
        'plan_id',
        'kind',
        'coverage_type',
        'coverage_value',
        'max_per_visit',
        'max_annual',
        'requires_preauth',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'requires_preauth' => 'bool',
        'coverage_value' => 'decimal:3',
        'max_per_visit' => 'decimal:3',
        'max_annual' => 'decimal:3',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'plan_id');
    }
}
