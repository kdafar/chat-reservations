<?php

namespace App\Models\Lab;

use App\Models\Concerns\LogsClinicActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabOrderItem extends Model
{
    use LogsClinicActivity;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FLAG_NORMAL = 'normal';
    public const FLAG_LOW = 'low';
    public const FLAG_HIGH = 'high';
    public const FLAG_CRITICAL = 'critical';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $activityLogName = 'lab_order_items';

    protected $casts = [
        'price_snapshot' => 'decimal:3',
        'result_numeric' => 'decimal:4',
        'ref_low' => 'decimal:4',
        'ref_high' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** True once the technician has put a value (or a text result) in. */
    public function hasResult(): bool
    {
        return trim((string) $this->result_value) !== '';
    }

    /**
     * Auto-flag a numeric result against the parsed reference bounds. Returns
     * null when we can't decide (non-numeric result, or no numeric range on the
     * catalog row) — the technician then picks the flag by hand.
     */
    public function deriveFlag(): ?string
    {
        if ($this->result_numeric === null) {
            return null;
        }
        $value = (float) $this->result_numeric;
        $low = $this->ref_low !== null ? (float) $this->ref_low : null;
        $high = $this->ref_high !== null ? (float) $this->ref_high : null;

        if ($low === null && $high === null) {
            return null;
        }
        if ($low !== null && $value < $low) {
            return self::FLAG_LOW;
        }
        if ($high !== null && $value > $high) {
            return self::FLAG_HIGH;
        }

        return self::FLAG_NORMAL;
    }

    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    /**
     * Use withTrashed so historical orders can still resolve the test name
     * after a catalog entry has been soft-deleted (archived).
     */
    public function labTest(): BelongsTo
    {
        return $this->belongsTo(LabTest::class)->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
