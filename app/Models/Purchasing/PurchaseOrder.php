<?php

namespace App\Models\Purchasing;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Accounting\Vendor;
use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An international purchase order raised to a vendor. Lifecycle:
 *
 *   draft → pending_approval → approved → sent → acknowledged
 *         → partially_received → received → closed
 *   (pending_approval → rejected; most states → cancelled)
 *
 * Money: line costs are in the PO `currency`; `exchange_rate` converts to KWD
 * (KWD per 1 unit). Landed costs (freight/customs/clearance/insurance/other)
 * are KWD and capitalise into inventory on receipt — see PurchaseService.
 */
class PurchaseOrder extends Model
{
    use LogsClinicActivity;

    use BelongsToBranchScope;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'vendor_id' => 'integer',
        'branch_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'order_date' => 'date',
        'expected_date' => 'date',
        'ship_date' => 'date',
        'eta' => 'date',
        'payment_terms_days' => 'integer',
        'payment_due_date' => 'date',
        'last_payment_reminder_at' => 'datetime',
        'subtotal' => 'decimal:3',
        'goods_total' => 'decimal:3',
        'goods_total_kwd' => 'decimal:3',
        'freight_amount' => 'decimal:3',
        'customs_amount' => 'decimal:3',
        'clearance_amount' => 'decimal:3',
        'insurance_amount' => 'decimal:3',
        'other_charges_amount' => 'decimal:3',
        'landed_total' => 'decimal:3',
        'total' => 'decimal:3',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** Value of goods received so far in KWD (the amount that hit Accounts Payable). Excludes reversed receipts. */
    public function amountReceived(): float
    {
        return (float) $this->receipts()->whereNull('reversed_at')->sum('total_amount');
    }

    /** Landed cost capitalised so far (KWD), excluding reversed receipts. */
    public function landedCapitalised(): float
    {
        return (float) $this->receipts()->whereNull('reversed_at')->sum('landed_amount');
    }

    /** Total paid to the vendor against this PO (KWD). */
    public function amountPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /** Outstanding vendor AP for this PO = goods received − paid (KWD). */
    public function outstanding(): float
    {
        return round($this->amountReceived() - $this->amountPaid(), 3);
    }

    public function isForeign(): bool
    {
        return strtoupper((string) $this->currency) !== 'KWD';
    }

    /** Days until the vendor payment is due (negative = overdue). Null if no due date / nothing owed. */
    public function daysUntilDue(): ?int
    {
        if (! $this->payment_due_date || $this->outstanding() <= 0) {
            return null;
        }

        return (int) round(now()->startOfDay()->diffInDays($this->payment_due_date->copy()->startOfDay(), false));
    }

    public function isOverdue(): bool
    {
        $d = $this->daysUntilDue();

        return $d !== null && $d < 0;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_ACKNOWLEDGED, self::STATUS_PARTIALLY_RECEIVED], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL, self::STATUS_APPROVED,
            self::STATUS_REJECTED, self::STATUS_SENT, self::STATUS_ACKNOWLEDGED,
        ], true);
    }

    public static function generateCode(Carbon|string $date): string
    {
        $d = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $prefix = 'PO-'.$d->format('Ymd');
        $count = self::withTrashed()->withoutGlobalScopes()->where('code', 'like', $prefix.'-%')->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
