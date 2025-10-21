<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class City extends Model
{
    use HasTranslations;

    protected $fillable = ['state_id', 'name', 'slug', 'latitude', 'longitude', 'is_active'];

    public $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    public function scopeOrderByLocale($q, ?string $locale = null)
    {
        $locale = $locale ?: app()->getLocale();

        return $q->orderBy("name->$locale");
    }
}
