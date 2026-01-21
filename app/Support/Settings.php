<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    /** How long (seconds) to cache single-key lookups */
    protected const TTL = 60;

    /** Get a setting. Tries exact key row first; then bucket fallback (root.path). */
    public static function get(string $key, mixed $default = null): mixed
    {
        // 1) Exact-key row (your current schema)
        $exact = Cache::remember("sysset:exact:{$key}", self::TTL, function () use ($key) {
            $row = SystemSetting::where('key', $key)->first();

            return $row?->value;
        });

        if ($exact !== null && $exact !== '') {
            return self::parse($exact, $default);
        }

        // 2) Bucket fallback: "root.path" -> row key = "root", JSON object in value
        if (str_contains($key, '.')) {
            [$root, $path] = explode('.', $key, 2);
            $bucket = Cache::remember("sysset:bucket:{$root}", self::TTL, function () use ($root) {
                $row = SystemSetting::where('key', $root)->first();

                return $row?->value;
            });

            if ($bucket !== null && $bucket !== '') {
                $decoded = self::parseJsonOrNull($bucket);
                if (is_array($decoded)) {
                    return data_get($decoded, $path, $default);
                }
            }
        }

        return $default;
    }

    /** Set a setting as an exact key row (recommended for your current schema). */
    public static function set(string $key, mixed $value): void
    {
        $row = SystemSetting::firstOrNew(['key' => $key]);
        $row->value = self::storeValue($value);
        $row->save();

        Cache::forget("sysset:exact:{$key}");

        // also clear bucket cache if this looks like "root.path"
        if (str_contains($key, '.')) {
            [$root] = explode('.', $key, 2);
            Cache::forget("sysset:bucket:{$root}");
        }
    }

    /** Optional: set inside a JSON bucket row (root + nested path). */
    public static function setBucket(string $root, string $path, mixed $value): void
    {
        $row = SystemSetting::firstOrNew(['key' => $root]);
        $data = self::parseJsonOrNull($row->value) ?? [];
        data_set($data, $path, $value);
        $row->value = json_encode($data, JSON_UNESCAPED_UNICODE);
        $row->save();

        Cache::forget("sysset:bucket:{$root}");
    }

    /** Fetch all settings, optionally filtered by a prefix (e.g., 'whatsapp.rate_limit.'). */
    public static function all(?string $prefix = null): array
    {
        $rows = Cache::remember('sysset:all', self::TTL, function () {
            return SystemSetting::query()->get(['key', 'value'])->toArray();
        });

        $map = [];
        foreach ($rows as $r) {
            $val = self::parse($r['value'], null);
            $map[$r['key']] = $val;
        }

        if ($prefix) {
            return collect($map)
                ->filter(fn ($v, $k) => str_starts_with($k, $prefix))
                ->all();
        }

        return $map;
    }

    /** Typed helpers */
    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default);
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $v;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key, $default);

        return (int) (is_numeric($v) ? $v : $default);
    }

    public static function getFloat(string $key, float $default = 0.0): float
    {
        $v = self::get($key, $default);

        return (float) (is_numeric($v) ? $v : $default);
    }

    public static function getString(string $key, string $default = ''): string
    {
        $v = self::get($key, $default);
        if (is_array($v) || is_object($v)) {
            return $default;
        }

        return (string) $v;
    }

    public static function flushCache(?string $prefix = null): void
    {
        Cache::forget('sysset:all');
        if ($prefix) {
            // Best-effort: drop common buckets for this prefix root
            [$root] = explode('.', $prefix, 2);
            Cache::forget("sysset:bucket:{$root}");
        }
    }

    /* ---------------- internals ---------------- */

    /** Parse a stored value (string) into PHP: bool/number/JSON/str. */
    protected static function parse(string $raw, mixed $default): mixed
    {
        $s = trim($raw);

        // JSON object/array or quoted JSON string
        if ($decoded = self::parseJsonOrNull($s)) {
            return $decoded;
        }

        // JSON *string* that includes quotes like:  "Hello world"
        if (self::looksJsonString($s)) {
            $str = json_decode($s, true);

            return is_string($str) ? $str : $default;
        }

        // booleans
        $low = strtolower($s);
        if ($low === 'true') {
            return true;
        }
        if ($low === 'false') {
            return false;
        }

        // numbers
        if (is_numeric($s)) {
            return str_contains($s, '.') ? (float) $s : (int) $s;
        }

        return $s === '' ? $default : $s;
    }

    /** Return array/object if JSON; null otherwise. */
    protected static function parseJsonOrNull(string $s): mixed
    {
        $first = substr(ltrim($s), 0, 1);
        if ($first === '{' || $first === '[') {
            $decoded = json_decode($s, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    /** Detect `"quoted json string"` values. */
    protected static function looksJsonString(string $s): bool
    {
        $s = trim($s);

        return strlen($s) >= 2 && $s[0] === '"' && substr($s, -1) === '"';
    }

    /** Convert value to storable string (JSON for arrays/objects). */
    protected static function storeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
