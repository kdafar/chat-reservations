<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Model;

class AboutTemplate extends Model
{
    protected $connection = 'wa';
    protected $table = 'about_templates';

    protected $fillable = [
        'scope', 'code', 'locale', 'body_template', 'field_toggles',
        'max_caption_length', 'is_enabled',
    ];

    protected $casts = [
        'field_toggles' => 'array',
        'max_caption_length' => 'integer',
        'is_enabled' => 'boolean',
    ];

    /** Helper: scope by code+locale with fallback to first enabled */
    public static function for(string $scope, string $locale, ?string $code = null): ?self
    {
        $q = static::query()->where('scope', $scope)->where('is_enabled', true);
        if ($code) {
            return $q->where('code', $code)->where('locale', $locale)->first()
                ?? $q->where('code', $code)->first()
                ?? $q->where('locale', $locale)->first()
                ?? $q->first();
        }

        return $q->where('locale', $locale)->first() ?? $q->first();
    }
}
