<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Service extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'slug', 'icon', 'is_active'];

    public $translatable = ['name'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class);
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class);
    }

    public function getNameLabelAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale());
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
