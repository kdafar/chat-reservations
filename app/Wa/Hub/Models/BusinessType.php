<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class BusinessType extends Model
{
    protected $connection = 'wa';
    use HasFactory;
    use HasTranslations;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'business_types';

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public $translatable = ['name', 'category_label', 'vendor_label', 'description'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['slug', 'description', 'name', 'category_label', 'vendor_label', 'is_active', 'image_url'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the vendors associated with this business type.
     */
    public function vendors()
    {
        return $this->hasMany(Vendors::class, 'business_type_id');
    }

    public function cuisines(): HasMany
    {
        return $this->hasMany(Cuisine::class);
    }
}
