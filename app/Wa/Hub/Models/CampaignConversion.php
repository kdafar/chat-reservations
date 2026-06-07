<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a successful conversion from a promotional campaign.
 *
 * @property int $id
 * @property int $promotional_campaign_id
 * @property int $whatsapp_session_id
 * @property string $order_id_from_restaurant
 * @property-read \App\Models\PromotionalCampaign $campaign
 * @property-read \App\Hub\Models\WhatsappSession $session
 */
class CampaignConversion extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'promotional_campaign_id',
        'whatsapp_session_id',
        'order_id_from_restaurant',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionalCampaign::class, 'promotional_campaign_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class, 'whatsapp_session_id');
    }
}
