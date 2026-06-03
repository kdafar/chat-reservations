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
        'completed_at' => 'datetime',
    ];

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
