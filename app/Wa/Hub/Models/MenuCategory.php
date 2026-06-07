<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class MenuCategory extends Model
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
     * The restaurant this category belongs to.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Vendors::class, 'vendor_id');
    }

    /**
     * The items within this category.
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
