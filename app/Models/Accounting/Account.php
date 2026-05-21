<?php

namespace App\Models\Accounting;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Chart of Accounts entry. The full hierarchy of where money can be tracked.
 *
 * Account "type" determines the natural balance direction. For reports we sum
 * debits − credits (or credits − debits, depending on type) over a date range.
 */
class Account extends Model
{
    use SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code', 'name', 'type', 'parent_id', 'branch_id', 'currency',
        'is_active', 'is_system', 'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_COGS = 'cogs';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_CONTRA_ASSET = 'contra_asset';

    public const TYPE_CONTRA_LIABILITY = 'contra_liability';

    public const TYPE_CONTRA_REVENUE = 'contra_revenue';

    /** Accounts where Dr increases the balance (assets, expenses, COGS). */
    public const DEBIT_NORMAL_TYPES = [
        self::TYPE_ASSET,
        self::TYPE_EXPENSE,
        self::TYPE_COGS,
        self::TYPE_CONTRA_LIABILITY,
        self::TYPE_CONTRA_REVENUE,
    ];

    /** Accounts where Cr increases the balance (liabilities, equity, revenue). */
    public const CREDIT_NORMAL_TYPES = [
        self::TYPE_LIABILITY,
        self::TYPE_EQUITY,
        self::TYPE_REVENUE,
        self::TYPE_CONTRA_ASSET,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOfType(Builder $q, string|array $type): Builder
    {
        return $q->whereIn('type', (array) $type);
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, self::DEBIT_NORMAL_TYPES, true);
    }

    public function isCreditNormal(): bool
    {
        return in_array($this->type, self::CREDIT_NORMAL_TYPES, true);
    }

    /**
     * Balance as of a given date (inclusive). Sums posted-entry lines only.
     * Positive value always represents an increase in the account's natural direction.
     */
    public function balanceAt(?string $endDate = null): float
    {
        // Include BOTH 'posted' and 'reversed' status. Reversed originals
        // still have real lines on the books; the offsetting reversal entry
        // (which is itself 'posted') cancels them. Net = correct historical
        // balance, audit trail preserved.
        $q = $this->lines()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED]);

        if ($endDate) {
            $q->whereDate('journal_entries.entry_date', '<=', $endDate);
        }

        $debit = (float) (clone $q)->sum('journal_entry_lines.debit');
        $credit = (float) (clone $q)->sum('journal_entry_lines.credit');

        return $this->isDebitNormal() ? $debit - $credit : $credit - $debit;
    }

    /**
     * Balance change over a date range, signed in natural direction.
     */
    public function balanceBetween(string $from, string $to): float
    {
        $q = $this->lines()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED])
            ->whereBetween('journal_entries.entry_date', [$from, $to]);

        $debit = (float) (clone $q)->sum('journal_entry_lines.debit');
        $credit = (float) (clone $q)->sum('journal_entry_lines.credit');

        return $this->isDebitNormal() ? $debit - $credit : $credit - $debit;
    }

    public function getDisplayLabelAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }
}
