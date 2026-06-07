<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class HubProfile extends Model
{
    protected $connection = 'wa';
    use HasTranslations;

    protected $table = 'hub_profiles';

    protected $fillable = [
        'brand_id', 'channel', 'name', 'about', 'open_hours',
        'site_url', 'phone', 'email', 'logo_path', 'is_enabled',
        'version', 'created_by', 'updated_by',
    ];

    public $translatable = ['name', 'about', 'open_hours'];

    protected $casts = [
        'name' => 'array',
        'about' => 'array',
        'open_hours' => 'array',
        'is_enabled' => 'boolean',
        'version' => 'integer',
    ];
}
