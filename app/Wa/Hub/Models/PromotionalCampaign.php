<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionalCampaign extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'message_template_id',
        'status',
        'total_recipients',
        'sent_at',

        // NEW FIELDS FOR WHATSAPP META TEMPLATE FLOW
        'template_name',
        'template_details',
        'template_variables',
        'header_image_path',
        'default_locale',
        'scheduled_at',
        'send_rate_per_min',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'template_details' => 'array',
        'template_variables' => 'array',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Vendors::class);
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(CampaignConversion::class, 'promotional_campaign_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WhatsappSession::class, 'last_promotional_campaign_id');
    }

    /**
     * NEW: Recipients for this campaign.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(PromotionalCampaignRecipient::class, 'promotional_campaign_id');
    }

    public function getPendingCountAttribute(): int
    {
        return $this->recipients()->where('status', 'pending')->count();
    }

    public function getSentCountAttribute(): int
    {
        return $this->recipients()->where('status', 'sent')->count();
    }

    public function getDeliveredCountAttribute(): int
    {
        return $this->recipients()->where('status', 'delivered')->count();
    }

    public function getReadCountAttribute(): int
    {
        return $this->recipients()->where('status', 'read')->count();
    }

    public function getFailedCountAttribute(): int
    {
        return $this->recipients()->where('status', 'failed')->count();
    }

    public function getLimitedCountAttribute(): int
    {
        return $this->recipients()->where('status', 'limited')->count();
    }

    public function getUndeliverableCountAttribute(): int
    {
        return $this->recipients()->where('status', 'undeliverable')->count();
    }

    public function getExperimentBlockedCountAttribute(): int
    {
        return $this->recipients()->where('status', 'experiment_blocked')->count();
    }
}
