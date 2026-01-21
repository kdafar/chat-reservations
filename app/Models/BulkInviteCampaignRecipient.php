<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkInviteCampaignRecipient extends Model
{
    protected $table = 'bulk_invite_recipients';

    protected $fillable = [
        'bulk_invite_campaign_id',
        'user_id',
        'msisdn',
        'name',
        'locale',
        'source',
        'status',
        'wa_message_id',
        'error_message',
    ];

    protected $attributes = [
        'status' => 'pending',
        'locale' => 'en',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the campaign this recipient belongs to
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BulkInviteCampaign::class, 'bulk_invite_campaign_id');
    }

    /**
     * Mark recipient as sent
     */
    public function markSent(string $waMessageId): void
    {
        $this->update([
            'status' => 'sent',
            'wa_message_id' => $waMessageId,
            'error_message' => null,
        ]);
    }

    /**
     * Mark recipient as failed
     */
    public function markFailed(string $errorMsg): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMsg,
        ]);
    }

    public function scopeForCampaign($q, int $campaignId)
    {
        return $q->where('bulk_invite_campaign_id', $campaignId);
    }
}
