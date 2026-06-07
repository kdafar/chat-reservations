<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Addon extends Model
{
    protected $connection = 'wa';
    use HasFactory;
    use HasTranslations;

    public $translatable = ['name'];

    protected $guarded = [];

    protected $casts = [
        'price' => 'float',
    ];

    public function addonGroup(): BelongsTo
    {
        return $this->belongsTo(AddonGroup::class);
    }
}
