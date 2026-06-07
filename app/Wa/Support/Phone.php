<?php

namespace App\Wa\Support;

class Phone
{
    /**
     * Very simple normalizer:
     * - If starts with +, keep as is (only digits +).
     * - If local, prefix by country code based on preferred region.
     *
     * @param  bool  $mobileOnly  (not enforced strictly here, just signature compatible)
     * @return string|null E.164-like string or null if invalid
     */
    public static function parseToE164AcrossRegions(
        string $raw,
        array $allowedRegions,
        string $preferredRegion,
        bool $mobileOnly = true
    ): ?string {
        $number = preg_replace('/\D+/', '', $raw ?? '');

        if (! $number) {
            return null;
        }

        // If it already looks like international (starts with + or 00)
        if (str_starts_with($raw, '+')) {
            return '+'.$number;
        }

        if (str_starts_with($number, '00')) {
            return '+'.substr($number, 2);
        }

        // Basic country code map (you can extend)
        $codes = [
            'KW' => '965',
            'SA' => '966',
            'AE' => '971',
            'QA' => '974',
            'BH' => '973',
            'OM' => '968',
            'EG' => '20',
        ];

        $preferredRegion = strtoupper($preferredRegion);

        $code = $codes[$preferredRegion] ?? $codes['KW'];

        return '+'.$code.$number;
    }
}
