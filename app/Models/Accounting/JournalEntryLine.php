<?php

namespace App\Models\Accounting;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $fillable = [
        'journal_entry_id', 'account_id',
        'debit', 'credit', 'description',
        'branch_id', 'doctor_id', 'patient_id',
        'currency', 'exchange_rate', 'meta',
    ];

    protected $casts = [
        'debit' => 'decimal:3',
        'credit' => 'decimal:3',
        'exchange_rate' => 'decimal:6',
        'meta' => 'array',
    ];

    /**
     * Enforce the double-entry convention: exactly ONE of (debit, credit) is
     * non-zero per line. A line with both > 0 is bookkeeping garbage even if
     * the whole entry balances. (Audit follow-up #4.)
     */
    protected static function booted(): void
    {
        static::saving(function (self $line) {
            $debit = (float) ($line->debit ?? 0);
            $credit = (float) ($line->credit ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new \RuntimeException('JournalEntryLine: debit and credit must be non-negative.');
            }
            if ($debit > 0 && $credit > 0) {
                throw new \RuntimeException(
                    'JournalEntryLine: a single line cannot carry both debit and credit '
                    ."(debit={$debit}, credit={$credit}). Split into two lines."
                );
            }
            if ($debit == 0 && $credit == 0) {
                throw new \RuntimeException('JournalEntryLine: at least one of debit/credit must be > 0.');
            }
        });

        // Immutability lock: lines of a posted/reversed entry cannot be added,
        // changed or removed (audit follow-up). Lines are only ever written
        // while the parent entry is still a draft — postBalancedEntry() and
        // reverse() both build their lines before calling post().
        static::creating(fn (self $line) => self::assertParentMutable($line, 'add a line to'));
        static::updating(fn (self $line) => self::assertParentMutable($line, 'modify a line of'));
        static::deleting(fn (self $line) => self::assertParentMutable($line, 'delete a line of'));
    }

    protected static function assertParentMutable(self $line, string $verb): void
    {
        if (! $line->journal_entry_id) {
            return;
        }
        $status = JournalEntry::query()->whereKey($line->journal_entry_id)->value('status');
        if (in_array($status, [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED], true)) {
            throw new \RuntimeException("Cannot {$verb} a journal entry that is {$status}; reverse the entry instead.");
        }
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
