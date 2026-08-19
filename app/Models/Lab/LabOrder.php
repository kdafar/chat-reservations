<?php

namespace App\Models\Lab;

use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\Concerns\LogsClinicActivity;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabOrder extends Model
{
    use SoftDeletes;
    use BelongsToBranchScope;
    use LogsClinicActivity;

    public const STATUS_ORDERED = 'ordered';
    public const STATUS_SAMPLE_COLLECTED = 'sample_collected';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses the lab still has work to do on. */
    public const OPEN_STATUSES = [
        self::STATUS_ORDERED,
        self::STATUS_SAMPLE_COLLECTED,
        self::STATUS_IN_PROGRESS,
    ];

    public const PRIORITY_ROUTINE = 'routine';
    public const PRIORITY_URGENT = 'urgent';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'ordered',
        'priority' => 'routine',
    ];

    protected $activityLogName = 'lab_orders';

    protected $casts = [
        'ordered_at' => 'datetime',
        'sample_collected_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Generate LAB-YYYYMMDD-XXXXX style codes on create. Mirrors the
     * InsuranceService::generateClaimNumber pattern.
     */
    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if (empty($row->order_code)) {
                $row->order_code = self::nextOrderCode();
            }
            if (empty($row->ordered_at)) {
                $row->ordered_at = now();
            }
        });
    }

    public static function nextOrderCode(): string
    {
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $countToday = self::query()
                ->where('order_code', 'like', "LAB-{$datePart}-%")
                ->count();
            $seq = str_pad((string) ($countToday + 1 + $attempt), 5, '0', STR_PAD_LEFT);
            $candidate = "LAB-{$datePart}-{$seq}";
            if (! self::query()->where('order_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return "LAB-{$datePart}-".strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    public function sampleCollectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sample_collected_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    /**
     * Report files attached to this order — the analyser printout or scan the
     * technician uploaded, plus any generated report we archived. Stored as
     * PatientFile rows so they inherit PHI access logging + the patient
     * timeline instead of living in a lab-only table.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(\App\Models\PatientFile::class, 'lab_order_id')
            ->orderByDesc('created_at');
    }

    /** Orders the lab still has to work on. */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    /** True once a result has been released but the doctor hasn't signed off. */
    public function awaitingDoctorReview(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->reviewed_at === null;
    }

    /** Highest-severity flag across the order's tests — drives the red dot. */
    public function worstFlag(): ?string
    {
        $flags = $this->items->pluck('flag')->filter()->all();
        foreach ([LabOrderItem::FLAG_CRITICAL, LabOrderItem::FLAG_HIGH, LabOrderItem::FLAG_LOW] as $f) {
            if (in_array($f, $flags, true)) {
                return $f;
            }
        }

        return $flags ? LabOrderItem::FLAG_NORMAL : null;
    }
}
