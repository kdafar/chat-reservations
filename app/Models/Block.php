<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Block extends Model
{
    use HasTranslations;

    protected $fillable = ['city_id', 'name', 'code', 'is_active', 'latitude', 'longitude'];

    public $translatable = ['name'];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    public function scopeOrderByLocale($q, ?string $locale = null)
    {
        $locale = $locale ?: app()->getLocale();

        return $q->orderBy("name->$locale");
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_block')
            ->withPivot(['delivery_fee', 'min_order_amount', 'is_active'])
            ->withTimestamps();
    }
}
