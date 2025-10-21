<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class HomepageSection extends Model
{
    use HasTranslations;

    protected $table = 'homepage_sections';

    protected $fillable = [
        'title', 'subtitle', 'hero_image_path',
        'show_featured_cuisines', 'show_featured_partners', 'show_trending_items',
    ];

    public $translatable = ['title', 'subtitle'];

    protected $casts = [
        'title' => 'array',
        'subtitle' => 'array',
        'show_featured_cuisines' => 'boolean',
        'show_featured_partners' => 'boolean',
        'show_trending_items' => 'boolean',
    ];

    public function getTitleLabelAttribute(): string
    {
        return $this->getTranslation('title', app()->getLocale()) ?? '';
    }

    public function featuredCityLinks()
    {
        return $this->hasMany(HomepageSectionCity::class)
            ->orderBy('sort_order');
    }

    public function featuredCities()
    {
        return $this->belongsToMany(City::class, 'homepage_section_city')
            ->withPivot('sort_order')
            ->orderBy('homepage_section_city.sort_order');
    }
}
