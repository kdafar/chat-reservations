<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionalCampaignRecipient extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $table = 'promotional_campaign_recipients';

    protected $fillable = [
        'promotional_campaign_id',
        'msisdn',
        'status',
        'wa_message_id',
        'error_message',
        'name',
        'locale',
        'source',
        'sent_at',
        'delivered_at',
        'read_at',
        'wa_error_code',
        'wa_error_title',
        'wa_status_payload',
        'wa_conversation_id',
        'wa_pricing_model',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'wa_status_payload' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionalCampaign::class, 'promotional_campaign_id');
    }
}
