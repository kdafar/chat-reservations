<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Concerns\BelongsToPartnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A clinic moving stock between its branches (typically hub → branch).
 * Dispatching the transfer consumes from the source branch and restocks the
 * destination branch (see StockTransferService). Clinic-isolated by partner.
 */
class StockTransfer extends Model
{
    use LogsClinicActivity;

    use BelongsToPartnerScope;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'integer',
        'from_branch_id' => 'integer',
        'to_branch_id' => 'integer',
        'visit_id' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
