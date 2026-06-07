<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class AddonGroup extends Model
{
    protected $connection = 'wa';
    use HasFactory;
    use HasTranslations;

    public $translatable = ['name'];

    protected $guarded = [];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class);
    }
}
