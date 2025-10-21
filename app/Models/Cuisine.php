<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Cuisine extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'slug', 'image_path', 'is_active'];

    public $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class);
    }

    public function getNameLabelAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale());
    }
}
