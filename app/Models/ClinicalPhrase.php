<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchScope;
use App\Models\Concerns\LogsClinicActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable clinical snippet ("dot-phrase") that doctors tap to insert into a
 * visit's free-text fields. See the create_clinical_phrases migration for the
 * clinic-vs-doctor scope semantics.
 */
class ClinicalPhrase extends Model
{
    use SoftDeletes;
    use BelongsToBranchScope;
    use LogsClinicActivity;

    /** Routine usage-counter bumps aren't audit-worthy noise. */
    protected array $activityLogExcept = ['usage_count'];

    protected $guarded = [];

    protected $activityLogName = 'clinical_phrases';

    /** The visit fields a phrase can target. */
    public const FIELDS = [
        'chief_complaint',
        'examination',
        'diagnosis',
        'patient_instructions',
        'prescriptions',
        'lab_requests',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
