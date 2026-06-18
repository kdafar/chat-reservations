<?php

namespace App\Models\Accounting;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Branch;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An operational expense (rent, utilities, marketing spend, office supplies,
 * etc.). Lifecycle: draft → posted → void (via reversal).
 *
 * Posting builds a balanced journal entry:
 *   - Dr  Expense account (the FK `account_id`, e.g. 6030 Rent)
 *   - Cr  Cash/Bank (if `payment_account_id` set) OR Accounts Payable 2010 (if null)
 *
 * Void reverses the posted journal entry — it does NOT delete the row,
 * preserving the audit trail.
 */
class Expense extends Model
{
    use LogsClinicActivity;

    use SoftDeletes;

    protected $table = 'expenses';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'code',
        'expense_date',
        'vendor_id',
        'branch_id',
        'account_id',
        'payment_account_id',
        'amount',
        'description',
        'reference_no',
        'receipt_path',
        'status',
        'posted_at',
        'posted_by_user_id',
        'journal_entry_id',
        'meta',
    ];

    protected $casts = [
        'expense_date' => 'date:Y-m-d',
        'amount' => 'decimal:3',
        'posted_at' => 'datetime',
        'meta' => 'array',
    ];

    // -------------------------------------------------------------------------
    // RELATIONSHIPS
    // -------------------------------------------------------------------------

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    // -------------------------------------------------------------------------
    // SCOPES
    // -------------------------------------------------------------------------

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function scopePosted(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_POSTED);
    }

    // -------------------------------------------------------------------------
    // LIFECYCLE
    // -------------------------------------------------------------------------

    /**
     * Post this expense to the GL. Idempotent — re-calling on an already-posted
     * expense returns self without creating a duplicate journal entry.
     */
    public function post(?int $userId = null): self
    {
        if ($this->status === self::STATUS_POSTED && $this->journal_entry_id) {
            return $this;
        }

        if ((float) $this->amount <= 0) {
            throw new \RuntimeException('Expense amount must be greater than zero before posting.');
        }
        if (! $this->account_id) {
            throw new \RuntimeException('Expense account must be set before posting.');
        }

        // The AccountingService will fill in journal_entry_id, status, posted_at,
        // posted_by_user_id on success.
        app(AccountingService::class)->recordExpense($this, $userId);

        return $this->refresh();
    }

    /**
     * Reverse the posted journal entry and mark this expense as void.
     * Idempotent — voiding an already-void or draft expense is a no-op.
     */
    public function void(?int $userId = null): self
    {
        if ($this->status === self::STATUS_VOID) {
            return $this;
        }

        if ($this->status === self::STATUS_POSTED && $this->journal_entry_id) {
            $je = $this->journalEntry()->first();
            if ($je && $je->status === JournalEntry::STATUS_POSTED) {
                $je->reverse($userId, 'Expense voided');
            }
        }

        $this->forceFill(['status' => self::STATUS_VOID])->save();

        return $this;
    }

    // -------------------------------------------------------------------------
    // BOOT — auto-generate code on save
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            if (! $expense->code) {
                $expense->code = self::generateCode($expense->expense_date ?? now());
            }
        });
    }

    /**
     * EXP-YYYYMMDD-XXXX — sequential per day.
     */
    public static function generateCode(\Carbon\Carbon|string $date): string
    {
        $d = $date instanceof \Carbon\Carbon ? $date->copy() : \Carbon\Carbon::parse($date);
        $prefix = 'EXP-'.$d->format('Ymd');
        $count = self::withTrashed()->where('code', 'like', $prefix.'-%')->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
