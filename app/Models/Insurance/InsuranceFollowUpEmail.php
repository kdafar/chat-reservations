<?php

namespace App\Models\Insurance;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One follow-up email sent to one insurer — what was sent, to whom, covering
 * which claims, and whether the mailer took it. See the migration for why
 * failures are kept.
 */
class InsuranceFollowUpEmail extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** What the insurer's answer amounted to. */
    public const OUTCOMES = [
        'payment_promised',
        'documents_required',
        'partial',
        'rejected',
        'no_response',
        'other',
    ];

    // Eloquent would guess `insurance_follow_up_emails` from the class name.
    protected $table = 'insurance_followup_emails';

    protected $guarded = [];

    protected $casts = [
        'claim_ids' => 'array',
        'claim_numbers' => 'array',
        'meta' => 'array',
        'claim_count' => 'integer',
        'total_outstanding' => 'decimal:3',
        'sent_at' => 'datetime',
        'replied_at' => 'datetime',
        'promised_payment_date' => 'date',
        'promised_amount' => 'decimal:3',
        'reply_recorded_at' => 'datetime',
    ];

    public function replyRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_recorded_by_user_id');
    }

    /** Sent, and still nobody has written down what came back. */
    public function awaitingReply(): bool
    {
        return $this->succeeded() && $this->reply_outcome === null;
    }

    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
