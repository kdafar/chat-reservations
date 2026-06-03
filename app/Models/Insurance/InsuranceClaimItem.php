<?php

namespace App\Models\Insurance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InsuranceClaimItem extends Model
{
    protected $table = 'insurance_claim_items';

    protected $fillable = [
        'claim_id',
        'source_type',
        'source_id',
        'kind',
        'label',
        'qty',
        'unit_price_snapshot',
        'line_total',
        'claimed_amount',
        'approved_amount',
        'patient_copay_amount',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'qty' => 'decimal:3',
        'unit_price_snapshot' => 'decimal:3',
        'line_total' => 'decimal:3',
        'claimed_amount' => 'decimal:3',
        'approved_amount' => 'decimal:3',
        'patient_copay_amount' => 'decimal:3',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
