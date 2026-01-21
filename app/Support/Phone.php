<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class Phone
{
    /**
     * Parse and validate a phone number for a whitelist of regions.
     * Returns E.164 (e.g., +9655xxxxxxx) or null if invalid.
     *
     * Rules:
     *  - must be a valid number per libphonenumber
     *  - region must be in $allowedRegions (defaults to GCC + Egypt)
     *  - must be MOBILE or FIXED_LINE_OR_MOBILE
     *  - tries $preferredRegion first for local numbers (no +)
     *  - if still not valid, tries each allowed region
     */
    public static function parseToE164AcrossRegions(
        string $raw,
        array $allowedRegions = ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'],
        ?string $preferredRegion = 'KW',
        bool $mobileOnly = true
    ): ?string {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        // map of dialing codes for quick detection (digits-only inputs)
        $dialMap = [
            'KW' => '965',
            'SA' => '966',
            'AE' => '971',
            'QA' => '974',
            'BH' => '973',
            'OM' => '968',
            'EG' => '20',
        ];

        // Helper: validate a parsed number against our rules.
        $validate = static function ($num) use ($util, $allowedRegions, $mobileOnly): ?string {
            if (! $util->isValidNumber($num)) {
                return null;
            }

            $region = $util->getRegionCodeForNumber($num);
            if (! $region || ! in_array($region, $allowedRegions, true)) {
                return null;
            }

            $type = $util->getNumberType($num);
            if ($mobileOnly && ! in_array($type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true)) {
                return null;
            }

            return $util->format($num, PhoneNumberFormat::E164);
        };

        try {
            // Case 1: explicit international (+ or 00)
            if (str_starts_with($raw, '+') || str_starts_with($raw, '00')) {
                $num = $util->parse($raw, null);
                if ($e164 = $validate($num)) {
                    return $e164;
                }
            }

            // Case 1b: digits-only that already start with a known country code (e.g., "9655...")
            $digits = preg_replace('/\D+/', '', $raw) ?? '';
            if ($digits !== '') {
                foreach ($allowedRegions as $r) {
                    $code = $dialMap[$r] ?? null;
                    if ($code && str_starts_with($digits, $code) && strlen($digits) > strlen($code)) {
                        // Treat as international even without '+'
                        $num = $util->parse('+'.$digits, null);
                        if ($e164 = $validate($num)) {
                            return $e164;
                        }
                    }
                }
            }

            // Case 2: local number – try preferred region first
            if ($preferredRegion) {
                try {
                    $num = $util->parse($raw, $preferredRegion);
                    if ($e164 = $validate($num)) {
                        return $e164;
                    }
                } catch (NumberParseException) {
                    // fallthrough
                }
            }

            // Case 3: try all allowed regions
            foreach ($allowedRegions as $region) {
                try {
                    $num = $util->parse($raw, $region);
                    if ($e164 = $validate($num)) {
                        return $e164;
                    }
                } catch (NumberParseException) {
                    // try next
                }
            }
        } catch (NumberParseException) {
            // ignore
        }

        return null;
    }
}
