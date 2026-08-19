<?php

namespace App\Models\Insurance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One inbound message from an insurer. See the migration for why replies live
 * apart from the statement they answer.
 */
class InsuranceFollowUpEmailReply extends Model
{
    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_APPLIED = 'applied';

    protected $table = 'insurance_followup_email_replies';

    protected $guarded = [];

    protected $casts = [
        'received_at' => 'datetime',
        'meta' => 'array',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(InsuranceFollowUpEmail::class, 'followup_email_id');
    }

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }
}
