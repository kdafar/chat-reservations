<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

class Vendors extends Model
{
    protected $connection = 'wa';
    use HasFactory;
    use HasTranslations; // Use the trait

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vendors';

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = [
        'name',
        'description',
        'badge_label',
        'about_desc', 'open_hours',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_type_id',
        'name',
        'description',
        'logo_url',
        'api_base_url',
        'api_key',
        'owner_whatsapp_number',
        'is_visible_on_whatsapp',
        'whatsapp_media_id',
        'facebook_product_set_id',
        'points',
        'working_hours',
        'whatsapp_notifications_enabled',
        'whatsapp_sort_order',
        'badge_active',
        'badge_emoji',
        'badge_label',
        'about_enabled',
        'synonyms',
        'about_logo_path',
        'phone',
        'website',
        'open_hours',
        'about_desc',
        'about_template_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_visible_on_whatsapp' => 'boolean',
        'working_hours' => 'json',
        'badge_active' => 'boolean',
        'about_desc' => 'array',
        'open_hours' => 'array',
        'synonyms' => 'array',
        'about_enabled' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     * This makes 'is_open' available whenever you convert the model to an array or JSON.
     */
    protected $appends = ['is_open'];

    /**
     * Get the WhatsApp sessions for the restaurant.
     */
    public function whatsappSessions(): HasMany
    {
        return $this->hasMany(WhatsappSession::class, 'selected_vendor_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(RestaurantKeyword::class, 'vendor_id');
    }

    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class, 'cuisine_vendor', 'vendor_id', 'cuisine_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(HubBranch::class, 'vendor_id');
    }

    // --------------------------------------------------------------------
    // Method 1: The Accessor (for convenience)
    //  Allows you to check a single restaurant instance: $restaurant->is_open
    // --------------------------------------------------------------------
    public function getIsOpenAttribute(): bool
    {
        $workingHours = $this->working_hours;
        if (empty($workingHours)) {
            return false;
        }

        $now = Carbon::now('Asia/Kuwait');
        $dayOfWeek = strtolower($now->format('l'));

        $todaysHours = $workingHours[$dayOfWeek] ?? [];
        if (empty($todaysHours)) {
            return false;
        }

        foreach ($todaysHours as $slot) {
            try {
                $openTime = Carbon::parse($slot['open'], 'Asia/Kuwait');
                $closeTime = Carbon::parse($slot['close'], 'Asia/Kuwait');

                if ($closeTime->lessThan($openTime)) {
                    $closeTime->addDay();
                }
                if ($now->between($openTime, $closeTime, true)) {
                    return true;
                }
            } catch (\Exception $e) {
                // Log error but continue checking other slots
                \Illuminate\Support\Facades\Log::error('Error parsing restaurant working hours', [
                    'restaurant_id' => $this->id,
                    'hours_slot' => $slot,
                ]);

                continue;
            }
        }

        return false;
    }

    // --------------------------------------------------------------------
    //  Method 2: The Query Scope (for performance)
    //  Allows you to filter at the database level: Vendors::open()->get()
    // --------------------------------------------------------------------
    public function scopeOpen(Builder $query): void
    {
        // This method filters the collection after retrieval.
        // It's a balance of performance and readability without complex raw SQL.
        $query->whereNotNull('working_hours')->get()->filter(function ($restaurant) {
            return $restaurant->is_open;
        });
    }

    /**
     * Get the delivery fee for a specific city this restaurant serves.
     */
    public function getDeliveryFeeForCity(int $cityId): ?float
    {
        // Find the first delivery area that belongs to an active branch of this specific restaurant.
        $deliveryArea = DeliveryArea::where('city_id', $cityId)
            ->whereHas('branch', function ($branchQuery) {
                // Add a condition to ensure the branch belongs to the current restaurant ($this->id)
                $branchQuery->where('vendor_id', $this->id)
                    ->where('is_active', true);
            })
            ->first();

        return $deliveryArea ? (float) $deliveryArea->delivery_fee : null;
    }

    /**
     * Get the menu categories for the restaurant.
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class, 'vendor_id');
    }

    /**
     * Get all menu items for the restaurant.
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'vendor_id');
    }

    public function aboutTemplate()
    {
        return $this->belongsTo(AboutTemplate::class, 'about_template_id');
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
