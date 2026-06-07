<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Cuisine extends Model
{
    protected $connection = 'wa';
    use HasFactory;
    use HasTranslations;

    public $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description', 'business_type_id', 'is_active', 'image_url', 'whatsapp_media_id'];

    /**
     * The restaurants that belong to the cuisine.
     */
    public function restaurants()
    {
        return $this->belongsToMany(Vendors::class, 'cuisine_vendor', 'cuisine_id', 'vendor_id');
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
}
