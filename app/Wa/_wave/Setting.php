<?php

namespace Wave;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Minimal shim of Wave\Setting for the isolated WhatsApp module.
 * Backed by the module's own `settings` table on the `wa` connection.
 * Faithful to the parts the WhatsApp code uses: get(), ofCategory(),
 * plus standard Eloquent (where/updateOrCreate/pluck).
 */
class Setting extends Model
{
    protected $connection = 'wa';

    protected $table = 'settings';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted()
    {
        static::saved(fn () => Cache::forget('wa_module_settings'));
        static::deleted(fn () => Cache::forget('wa_module_settings'));
    }

    public static function get($key, $default = null)
    {
        $settings = Cache::rememberForever('wa_module_settings', function () {
            return self::pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    public static function ofCategory($category, $key, $default = null)
    {
        return static::where('category', $category)
            ->where('key', $key)
            ->value('value') ?? $default;
    }
}
