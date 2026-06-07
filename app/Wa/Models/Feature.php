<?php

namespace App\Wa\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Feature extends Model
{
    protected $connection = 'wa';
    use HasTranslations;

    public $translatable = ['title', 'description'];

    protected $fillable = [
        'title', 'description', 'image', 'sort_order', 'is_active',
    ];
}
