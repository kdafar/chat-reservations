<?php

namespace App\Models\Insurance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimStateLog extends Model
{
    protected $table = 'insurance_claim_state_logs';

    protected $fillable = [
        'claim_id',
        'from_status',
        'to_status',
        'notes',
        'changed_by_user_id',
        'changed_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'changed_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
