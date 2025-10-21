<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class State extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'slug', 'is_active'];

    public $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cities()
    {
        return $this->hasMany(City::class);
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
