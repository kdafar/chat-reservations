<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single row imported from (or manually keyed off) a bank statement.
 *
 * Sign convention is STATEMENT-side, NOT GL-side:
 *   debit  = money INTO our bank account (deposit / inflow / credit-to-us)
 *   credit = money OUT of our bank account (withdrawal / outflow / debit-from-us)
 *
 * When matched to a JournalEntryLine on the same cash/bank account, the
 * direction inverts (our bank inflow corresponds to a GL debit on the cash
 * account; our outflow corresponds to a GL credit). The match() method
 * encodes this swap-rule.
 */
class BankStatementLine extends Model
{
    protected $table = 'bank_statement_lines';

    protected $fillable = [
        'bank_reconciliation_id',
        'statement_date',
        'description',
        'reference',
        'debit',
        'credit',
        'matched_journal_entry_line_id',
        'matched_at',
        'matched_by_user_id',
        'notes',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'debit' => 'decimal:3',
        'credit' => 'decimal:3',
        'matched_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // RELATIONSHIPS
    // -------------------------------------------------------------------------

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function matchedLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'matched_journal_entry_line_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }

    // -------------------------------------------------------------------------
    // ACCESSORS
    // -------------------------------------------------------------------------

    public function getIsMatchedAttribute(): bool
    {
        return $this->matched_journal_entry_line_id !== null;
    }

    public function isMatched(): bool
    {
        return $this->matched_journal_entry_line_id !== null;
    }

    public function statementAmount(): float
    {
        return (float) $this->debit > 0
            ? (float) $this->debit
            : (float) $this->credit;
    }

    // -------------------------------------------------------------------------
    // MATCHING
    // -------------------------------------------------------------------------

    /**
     * Pair this bank line with a JournalEntryLine.
     *
     * On the cash/bank GL account itself the signs align DIRECTLY with the
     * bank statement's debit/credit columns: a deposit is a Dr on the cash
     * account, a withdrawal is a Cr. We enforce that strict same-side rule
     * here. The earlier impl also accepted swapped-side amounts, which
     * silently masked bookkeeping mistakes (e.g. an income entry that was
     * mis-posted with debit/credit swapped would still appear to match).
     *
     * Sanity checks (all throw \RuntimeException):
     *   - the JE line must exist
     *   - the JE line must belong to the reconciliation's account
     *   - the JE line must NOT already be matched by ANOTHER bank line
     *     (this prevents one GL entry from "covering" two bank lines)
     *   - the JE line's posted entry must not be reversed/draft
     *   - amounts must agree direction-by-direction on the SAME side
     */
    public function match(int $journalEntryLineId, ?int $userId = null): self
    {
        $line = JournalEntryLine::with('entry')->find($journalEntryLineId);
        if (! $line) {
            throw new \RuntimeException("Journal entry line #{$journalEntryLineId} not found.");
        }

        $rec = $this->reconciliation()->first();
        if (! $rec) {
            throw new \RuntimeException('Bank statement line has no reconciliation.');
        }

        if ((int) $line->account_id !== (int) $rec->account_id) {
            throw new \RuntimeException(
                "Account mismatch: JE line is on account #{$line->account_id} "
                ."but reconciliation is for account #{$rec->account_id}."
            );
        }

        // Only allow matching against POSTED entries — drafts and reversed
        // entries aren't real cash movements yet.
        if (($line->entry->status ?? null) !== \App\Models\Accounting\JournalEntry::STATUS_POSTED) {
            throw new \RuntimeException("JE line #{$line->id} is on a non-posted entry; cannot match.");
        }

        // Reject if another bank line in any reconciliation is already
        // pointing to this JE line. One GL entry → at most one bank line.
        $existingMatch = static::query()
            ->where('matched_journal_entry_line_id', $line->id)
            ->where('id', '!=', $this->id)
            ->first();
        if ($existingMatch) {
            throw new \RuntimeException(
                "JE line #{$line->id} is already matched to bank statement line #{$existingMatch->id}."
            );
        }

        $bankDebit = (float) $this->debit;
        $bankCredit = (float) $this->credit;
        $jeDebit = (float) $line->debit;
        $jeCredit = (float) $line->credit;

        $tolerance = 0.005;
        $sameSide = abs($bankDebit - $jeDebit) <= $tolerance
            && abs($bankCredit - $jeCredit) <= $tolerance;

        if (! $sameSide) {
            throw new \RuntimeException(
                'Amount mismatch (same-side required): bank='.number_format($bankDebit, 3)
                .'/'.number_format($bankCredit, 3)
                .' vs JE='.number_format($jeDebit, 3).'/'.number_format($jeCredit, 3)
            );
        }

        $this->forceFill([
            'matched_journal_entry_line_id' => $line->id,
            'matched_at' => now(),
            'matched_by_user_id' => $userId,
        ])->save();

        return $this;
    }

    /** Break the pairing. Idempotent. */
    public function unmatch(): self
    {
        $this->forceFill([
            'matched_journal_entry_line_id' => null,
            'matched_at' => null,
            'matched_by_user_id' => null,
        ])->save();

        return $this;
    }
}
