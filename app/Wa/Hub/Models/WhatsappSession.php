<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WhatsappSession extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_phone_number',
        'customer_name',
        'status',
        'flow_token',
        'selected_vendor_id',
        'notes',
        'delivery_address',
        'locale',
        'delivery_state_id',
        'delivery_city_id',
        'last_interacted_at',
        'flow_street',
        'flow_block_id',
        'flow_house_no',
        'is_blocked',
        'promo_code',
        'last_promotional_campaign_id',
        'direct_intent_restaurant_id',
        'direct_intent_cuisine_id',
        'direct_intent_business_type_id',
    ];

    /**
     * Get the restaurant that the user has selected for this session.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Vendors::class, 'selected_vendor_id');
    }

    /**
     * Get the cart items for this WhatsApp session.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the ratings submitted during this session.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function lastPromotionalCampaign(): BelongsTo
    {
        return $this->belongsTo(PromotionalCampaign::class, 'last_promotional_campaign_id');
    }

    public function scopeByFlowToken($query, string $token)
    {
        return $query->where('flow_token', $token);
    }

    public function scopeLatestForPhone($query, string $phone)
    {
        return $query->where('customer_phone_number', $phone)
            ->latest('updated_at');
    }

    public function deliveryState()
    {
        return $this->belongsTo(State::class, 'delivery_state_id');
    }

    public function deliveryCity()
    {
        return $this->belongsTo(City::class, 'delivery_city_id');
    }
}
