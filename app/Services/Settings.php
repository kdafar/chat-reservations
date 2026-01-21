<?php

namespace App\Services;

use App\Models\SystemSetting;

class Settings
{
    public function get(string $key, $default = null)
    {
        return optional(SystemSetting::where('key', $key)->first())->value ?? $default;
    }
}
