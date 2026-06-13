<?php

namespace App\Services\Clinic;

use Illuminate\Support\Carbon;

/**
 * End-of-service indemnity, Kuwait Private Sector Labour Law No. 6/2010,
 * Article 51 — MONTHLY-salaried worker basis:
 *
 *   - daily wage     = basic monthly salary / 26 working days
 *   - first 5 years  = 15 days' wage per year       (15 × daily)
 *   - beyond 5 years = one month's wage per year     (= one full monthly salary)
 *   - total capped at 1.5 years' (18 months') wage
 *
 * (The "10/15 days" rate cited by some calculators is the Art. 51 rate for
 *  DAILY/hourly/piece-rate workers, capped at one year — not salaried staff.)
 *
 * Resignation on an indefinite-term contract reduces the indemnity:
 *   - service < 3y      : NIL (no indemnity)
 *   - 3y ≤ service < 5y : ½ deducted        → 50% paid
 *   - 5y ≤ service <10y : ⅓ deducted        → ⅔ paid
 *   - service ≥ 10y     : full indemnity
 * Termination / non-renewal by the employer pays the full indemnity.
 *
 * Computed numbers are a defensible DEFAULT; the settlement form lets HR
 * override the final figure for contractual or edge-case differences.
 */
class GratuityService
{
    public const WORKING_DAYS_PER_MONTH = 26;
    public const CAP_MONTHS = 18; // 1.5 years' wage

    public function yearsOfService(string|Carbon $hireDate, string|Carbon $lastDay): float
    {
        $a = $hireDate instanceof Carbon ? $hireDate : Carbon::parse($hireDate);
        $b = $lastDay instanceof Carbon ? $lastDay : Carbon::parse($lastDay);
        if ($b->lessThan($a)) {
            return 0.0;
        }

        // Inclusive of the last working day.
        return round(($a->diffInDays($b) + 1) / 365.25, 4);
    }

    /**
     * @param  float  $basicSalary  monthly basic wage (KWD)
     * @param  float  $years  years of service
     * @param  string  $mode  'termination' (full) | 'resignation' (reduced)
     */
    public function gratuity(float $basicSalary, float $years, string $mode = 'termination'): float
    {
        if ($basicSalary <= 0 || $years <= 0) {
            return 0.0;
        }

        $dailyWage = $basicSalary / self::WORKING_DAYS_PER_MONTH;

        $firstChunk = min($years, 5.0);
        $secondChunk = max(0.0, $years - 5.0);

        // First 5y: 15 days/yr. After: one month's wage/yr (= one monthly salary).
        $gross = ($firstChunk * 15 * $dailyWage) + ($secondChunk * $basicSalary);

        // Cap at 1.5 years' (18 months') wage.
        $gross = min($gross, self::CAP_MONTHS * $basicSalary);

        if ($mode === 'resignation') {
            $gross *= $this->resignationFactor($years);
        }

        return round($gross, 3);
    }

    /** Fraction of the full indemnity payable on resignation (indefinite contract). */
    public function resignationFactor(float $years): float
    {
        return match (true) {
            $years < 3 => 0.0,   // no indemnity below 3 years
            $years < 5 => 0.5,   // half deducted
            $years < 10 => 2 / 3, // one-third deducted
            default => 1.0,
        };
    }
}
