<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitStockRequest extends Model
{
    use LogsClinicActivity;

    use BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'visit_id' => 'integer',
        'branch_id' => 'integer',
        'requested_by_user_id' => 'integer',
        'fulfilled_by_user_id' => 'integer',
        'fulfilled_at' => 'datetime',
        'received_by_user_id' => 'integer',
        'received_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    /** Store has issued the stock (inventory consumed) but the doctor has not yet confirmed receipt → not billed. */
    public const STATUS_FULFILLED = 'fulfilled';

    /** Doctor confirmed receipt; the received quantities are billed to the patient. */
    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VisitStockRequestLine::class, 'visit_stock_request_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }
}
