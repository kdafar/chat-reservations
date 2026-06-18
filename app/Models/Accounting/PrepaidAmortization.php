<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One month's posted amortization for a prepaid schedule (idempotency ledger). */
class PrepaidAmortization extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:3',
        'period_end' => 'date',
    ];

    public function prepaidSchedule(): BelongsTo
    {
        return $this->belongsTo(PrepaidSchedule::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
