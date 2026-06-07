<?php

namespace App\Wa\Support;

use Illuminate\Support\Str;

class AddressBook
{
    public static function all(array $addresses): array
    {
        return $addresses ?? [];
    }

    public static function get(array $addresses, string $slug): ?array
    {
        return collect($addresses)->firstWhere('slug', $slug);
    }

    public static function upsert(array &$addresses, array $new): void
    {
        // Ensure slug & label exist
        if (empty($new['slug'])) {
            $new['slug'] = self::makeSlug($new);
        }
        if (empty($new['label'])) {
            $new['label'] = self::defaultLabel($new);
        }

        $slug = $new['slug'];
        $found = false;

        foreach ($addresses as &$addr) {
            if (($addr['slug'] ?? null) === $slug) {
                $addr = $new;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $addresses[] = $new;
        }
    }

    public static function remove(array &$addresses, string $slug): void
    {
        $addresses = collect($addresses)
            ->reject(fn ($a) => ($a['slug'] ?? null) === $slug)
            ->values()
            ->all();
    }

    private static function makeSlug(array $a): string
    {
        // home, office, other, etc. fallback
        $base = $a['slug'] ?? $a['address_type'] ?? 'addr';
        $city = $a['city_id'] ?? '';
        $blk = $a['block_id'] ?? '';
        $str = $a['street'] ?? '';

        $raw = trim($base.'-'.$city.'-'.$blk.'-'.$str);
        $slug = Str::slug($raw) ?: 'addr';

        // Add random tail to guarantee uniqueness
        return $slug.'-'.Str::random(5);
    }

    private static function defaultLabel(array $a): string
    {
        return $a['address_type_label']
            ?? ucfirst($a['address_type'] ?? 'Address');
    }
}
