<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class MenuItem extends Model
{
    protected $connection = 'wa';
    use HasFactory;
    use HasTranslations;

    public $translatable = ['name', 'description'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * The restaurant this item belongs to.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Vendors::class, 'vendor_id');
    }

    /**
     * The category this item belongs to.
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * The addon groups available for this item.
     */
    public function addonGroups(): HasMany
    {
        return $this->hasMany(AddonGroup::class);
    }
}
