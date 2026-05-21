<?php

namespace App\Models\Accounting;

use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

/**
 * A double-entry journal entry. Every clinic event that affects accounts
 * produces (at least) one of these, with balanced debit/credit lines.
 *
 * Lifecycle:
 *   draft → posted (immutable from here)
 *   posted → reversed (the original is marked reversed when an offsetting
 *                      reversal entry is created and links back via reversed_by_id)
 */
class JournalEntry extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'code', 'entry_date', 'narration', 'status',
        'posted_at', 'posted_by_user_id',
        'reversed_by_id', 'reversal_of_id',
        'source_type', 'source_id',
        'accounting_period_id', 'branch_id', 'currency',
        'meta',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /** The reversal entry that offset this one (if any). */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    /** If this entry IS a reversal, the original it offsets. */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function isBalanced(float $tolerance = 0.001): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) <= $tolerance;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Post the entry: stamp it, freeze it, attach period.
     * Throws if debits ≠ credits or if the target period is closed.
     */
    public function post(?int $userId = null): self
    {
        if ($this->isPosted()) {
            return $this;
        }

        $this->load('lines');

        if (! $this->isBalanced()) {
            throw new \RuntimeException(
                "Journal entry {$this->code} is unbalanced: debits=".number_format($this->totalDebit(), 3)
                .', credits='.number_format($this->totalCredit(), 3)
            );
        }

        $period = AccountingPeriod::forDate($this->entry_date);
        if ($period->isClosed()) {
            throw new \RuntimeException("Period {$period->code} is closed; cannot post entries dated within it.");
        }

        $this->forceFill([
            'status' => self::STATUS_POSTED,
            'posted_at' => now(),
            'posted_by_user_id' => $userId,
            'accounting_period_id' => $period->id,
            'code' => $this->code ?: self::generateCode($this->entry_date),
        ])->save();

        return $this;
    }

    /**
     * Create a reversal: a new entry with the same lines, debits and credits swapped.
     * The original is marked status='reversed'.
     */
    public function reverse(?int $userId = null, ?string $reason = null): self
    {
        if (! $this->isPosted()) {
            throw new \RuntimeException("Only posted entries can be reversed (this is {$this->status}).");
        }
        if ($this->reversed_by_id) {
            throw new \RuntimeException("Entry {$this->code} has already been reversed.");
        }

        return DB::transaction(function () use ($userId, $reason) {
            $this->load('lines');

            $this->forceFill([
                'status' => self::STATUS_REVERSED,
            ])->save();

            // The reversal entry deliberately does NOT carry the source
            // link. If it did, the (source_type, source_id, status='posted')
            // unique index would block any subsequent re-accrual against the
            // same source (e.g. doctor cut recomputed after correction), and
            // postBalancedEntry()'s 23000-catch would silently hand the
            // reversal back to the caller as if it were the new entry.
            // The original's source link stays put; traceability between
            // original ↔ reversal is preserved via reversed_by_id and the
            // 'reversal_of' / 'original_source' meta keys.
            $reversal = self::create([
                'entry_date' => now()->toDateString(),
                'narration' => 'Reversal of '.$this->code.($reason ? " — {$reason}" : ''),
                'status' => self::STATUS_DRAFT,
                'source_type' => null,
                'source_id' => null,
                'reversal_of_id' => $this->id,
                'branch_id' => $this->branch_id,
                'currency' => $this->currency,
                // meta keys retained for backwards-compat with rows written
                // before the reversal_of_id column existed.
                'meta' => array_merge((array) $this->meta, [
                    'reversal_of' => $this->id,
                    'original_source_type' => $this->source_type,
                    'original_source_id' => $this->source_id,
                ]),
            ]);

            foreach ($this->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,   // swap
                    'credit' => $line->debit,   // swap
                    'description' => 'Reversal: '.($line->description ?? ''),
                    'branch_id' => $line->branch_id,
                    'doctor_id' => $line->doctor_id,
                    'patient_id' => $line->patient_id,
                    'currency' => $line->currency,
                    'exchange_rate' => $line->exchange_rate,
                ]);
            }

            $reversal->post($userId);

            $this->forceFill([
                'reversed_by_id' => $reversal->id,
            ])->save();

            return $reversal;
        });
    }

    public static function generateCode(Carbon|string $entryDate): string
    {
        $d = $entryDate instanceof Carbon ? $entryDate->copy() : Carbon::parse($entryDate);
        $prefix = 'JE-'.$d->format('Ymd');
        $count = self::where('code', 'like', $prefix.'-%')->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
