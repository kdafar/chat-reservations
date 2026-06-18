<?php

namespace App\Models\Purchasing;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Vendor;
use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A payment made to a vendor against a purchase order.
 * Posts Dr Accounts Payable / Cr Cash or Bank.
 */
class PurchasePayment extends Model
{
    use LogsClinicActivity;

    use BelongsToBranchScope;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'branch_id' => 'integer',
        'vendor_id' => 'integer',
        'amount' => 'decimal:3',
        'payment_date' => 'date',
        'payment_account_id' => 'integer',
        'journal_entry_id' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public static function generateCode(Carbon|string $date): string
    {
        $d = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $prefix = 'PAY-'.$d->format('Ymd');
        $count = self::withTrashed()->withoutGlobalScopes()->where('code', 'like', $prefix.'-%')->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
