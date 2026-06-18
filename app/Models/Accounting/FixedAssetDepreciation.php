<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One month's posted depreciation for a fixed asset (idempotency ledger). */
class FixedAssetDepreciation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:3',
        'period_end' => 'date',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
