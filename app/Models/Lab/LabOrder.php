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

    protected $guarded = [];

    protected $attributes = [
        'status' => 'ordered',
    ];

    protected $activityLogName = 'lab_orders';

    protected $casts = [
        'ordered_at' => 'datetime',
        'sample_collected_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }
}
