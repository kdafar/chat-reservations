<?php

namespace App\Models\Purchasing;

use App\Models\Accounting\JournalEntry;
use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A goods-received note (GRN): one receiving event against a purchase order.
 * Each receipt restocks the branch and posts Dr Inventory / Cr Accounts Payable.
 */
class PurchaseReceipt extends Model
{
    use BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'branch_id' => 'integer',
        'received_at' => 'datetime',
        'reversed_at' => 'datetime',
        'total_amount' => 'decimal:3',
        'landed_amount' => 'decimal:3',
        'journal_entry_id' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public static function generateCode(Carbon|string $date): string
    {
        $d = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $prefix = 'GRN-'.$d->format('Ymd');
        $count = self::withoutGlobalScopes()->where('code', 'like', $prefix.'-%')->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
