<?php

namespace App\Models\Accounting;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bank reconciliation: pairs a bank/cash account's journal-entry lines with
 * the entries on the corresponding bank statement over a given period.
 *
 * Lifecycle:
 *   in_progress — accountant is uploading statement lines and matching them
 *   completed   — frozen; only admins can reopen
 *
 * Successful reconciliation = every statement line is matched to exactly one
 * journal-entry line, AND closing_balance == book_closing_balance.
 */
class BankReconciliation extends Model
{
    use SoftDeletes;

    protected $table = 'bank_reconciliations';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'code',
        'account_id',
        'period_start',
        'period_end',
        'opening_balance',
        'closing_balance',
        'book_opening_balance',
        'book_closing_balance',
        'status',
        'completed_at',
        'completed_by_user_id',
        'notes',
        'meta',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:3',
        'closing_balance' => 'decimal:3',
        'book_opening_balance' => 'decimal:3',
        'book_closing_balance' => 'decimal:3',
        'completed_at' => 'datetime',
        'meta' => 'array',
    ];

    // -------------------------------------------------------------------------
    // RELATIONSHIPS
    // -------------------------------------------------------------------------

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_reconciliation_id');
    }

    /** Statement lines that have been paired to a journal entry line. */
    public function matchedLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_reconciliation_id')
            ->whereNotNull('matched_journal_entry_line_id');
    }

    // -------------------------------------------------------------------------
    // BOOT — auto-generate code on save
    // -------------------------------------------------------------------------

    protected static function booted(): void
    {
        static::saving(function (self $rec) {
            if (! $rec->code) {
                $rec->code = self::generateCode($rec->period_start ?? now());
            }
        });
    }

    /**
     * BR-YYYYMMDD-XXXX — sequential per period_start day.
     */
    public static function generateCode(Carbon|string $date): string
    {
        $d = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $prefix = 'BR-'.$d->format('Ymd');
        $count = self::withTrashed()->where('code', 'like', $prefix.'-%')->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // BALANCE CALCULATION
    // -------------------------------------------------------------------------

    /**
     * Recompute the book opening/closing balances from posted journal entry
     * lines that hit this->account_id. Does NOT save — caller must persist.
     *
     * Opening: balance as of (period_start - 1 day) — i.e. cumulative before
     *          the first day of the period.
     * Closing: balance as of period_end (inclusive).
     */
    public function recomputeBookBalances(): void
    {
        $account = $this->account()->first();
        if (! $account) {
            $this->book_opening_balance = 0;
            $this->book_closing_balance = 0;

            return;
        }

        $start = $this->period_start instanceof Carbon
            ? $this->period_start->copy()
            : Carbon::parse($this->period_start);
        $end = $this->period_end instanceof Carbon
            ? $this->period_end->copy()
            : Carbon::parse($this->period_end);

        $openingAsOf = $start->copy()->subDay()->toDateString();
        $closingAsOf = $end->copy()->toDateString();

        $this->book_opening_balance = round($account->balanceAt($openingAsOf), 3);
        $this->book_closing_balance = round($account->balanceAt($closingAsOf), 3);
    }

    // -------------------------------------------------------------------------
    // UNRECONCILED BOOK LINES
    // -------------------------------------------------------------------------

    /**
     * Collection of JournalEntryLine rows in [period_start, period_end] that
     * hit this->account_id and are NOT yet matched to any statement line in
     * this reconciliation. Used by the "Match to JE" picker.
     */
    public function getUnreconciledBookLinesAttribute(): Collection
    {
        if (! $this->account_id || ! $this->period_start || ! $this->period_end) {
            return new Collection;
        }

        $matchedIds = $this->statementLines()
            ->whereNotNull('matched_journal_entry_line_id')
            ->pluck('matched_journal_entry_line_id')
            ->all();

        return JournalEntryLine::query()
            ->where('account_id', $this->account_id)
            ->whereHas('entry', function ($q) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                    ->whereBetween('entry_date', [
                        $this->period_start->toDateString(),
                        $this->period_end->toDateString(),
                    ]);
            })
            ->when(! empty($matchedIds), fn ($q) => $q->whereNotIn('id', $matchedIds))
            ->with('entry')
            ->get();
    }
}
