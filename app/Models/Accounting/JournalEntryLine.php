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
