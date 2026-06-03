<?php

namespace App\Services\Insurance;

use App\Models\Insurance\InsuranceCoverageRule;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * Pure calculator: given a visit + ordered policies, returns the
 * patient/insurer split per kind. No persistence. The service layer
 * uses this to seed a claim's totals; the UI uses it for previews.
 *
 * Cascading rule: each policy applies in priority order (primary first)
 * to the *remaining* uncovered gross. Whatever the cascade can't cover
 * falls to the patient.
 */
class CoverageCalculator
{
    /**
     * Per-policy rule lookup cache, indexed by "plan_id:kind" so a multi-kind
     * visit doesn't re-query for the same plan four times.
     *
     * @var array<string, InsuranceCoverageRule|null>
     */
    protected array $ruleCache = [];

    /**
     * Compute the patient vs insurer split per kind for a visit.
     *
     * @param  Collection<int, PatientInsurancePolicy>  $policies  Ordered by priority (1=primary first)
     * @return array{
     *   by_kind: array<string, array{kind: string, gross: float, patient_copay: float, insurer_portions: array<int, array{policy_id: int, amount: float}>}>,
     *   totals: array{gross: float, patient_total: float, insurer_total: float}
     * }
     */
    public function estimate(Visit $visit, $policies): array
    {
        $policies = $policies instanceof Collection
            ? $policies
            : collect($policies);

        $buckets = $this->bucketGross($visit);

        $byKind = [];
        $totalGross = 0.0;
        $totalPatient = 0.0;
        $totalInsurer = 0.0;

        foreach ($buckets as $kind => $gross) {
            $gross = round((float) $gross, 3);
            $remaining = $gross;
            $insurerPortions = [];

            foreach ($policies as $policy) {
                if ($remaining <= 0.001) {
                    break;
                }

                $rule = $this->ruleFor($policy, $kind);
                $portion = $this->applyRule($rule, $remaining);
                $portion = round($portion, 3);

                if ($portion <= 0) {
                    // No matching rule for this kind on this policy — skip.
                    continue;
                }

                // Defensive cap: can't pay more than what's still on the table.
                $portion = min($portion, $remaining);

                $insurerPortions[] = [
                    'policy_id' => (int) $policy->id,
                    'amount' => round($portion, 3),
                ];

                $remaining = round($remaining - $portion, 3);
            }

            $patientCopay = round(max(0.0, $remaining), 3);
            $insurerTotalForKind = round(array_sum(array_column($insurerPortions, 'amount')), 3);

            $byKind[$kind] = [
                'kind' => $kind,
                'gross' => $gross,
                'patient_copay' => $patientCopay,
                'insurer_portions' => $insurerPortions,
            ];

            $totalGross = round($totalGross + $gross, 3);
            $totalPatient = round($totalPatient + $patientCopay, 3);
            $totalInsurer = round($totalInsurer + $insurerTotalForKind, 3);
        }

        return [
            'by_kind' => $byKind,
            'totals' => [
                'gross' => $totalGross,
                'patient_total' => $totalPatient,
                'insurer_total' => $totalInsurer,
            ],
        ];
    }

    /**
     * Apply a single rule to a gross amount, returning the insurer's portion.
     * Honors max_per_visit cap. Returns 0 if rule is null (uncovered kind).
     */
    public function applyRule(?InsuranceCoverageRule $rule, float $gross): float
    {
        if (! $rule) {
            return 0.0;
        }

        if ($gross <= 0) {
            return 0.0;
        }

        $value = (float) ($rule->coverage_value ?? 0);
        $maxPerVisit = $rule->max_per_visit !== null
            ? (float) $rule->max_per_visit
            : null;

        $portion = match ($rule->coverage_type) {
            InsuranceCoverageRule::TYPE_PERCENTAGE => $gross * ($value / 100),
            InsuranceCoverageRule::TYPE_FIXED => min($value, $gross),
            InsuranceCoverageRule::TYPE_COPAY_AMOUNT => max(0.0, $gross - $value),
            default => 0.0,
        };

        if ($maxPerVisit !== null) {
            $portion = min($portion, $maxPerVisit);
        }

        return round(max(0.0, $portion), 3);
    }

    /**
     * Map a Visit's snapshot totals into per-kind gross buckets.
     *
     * VisitItems carry no kind column in this clinic — they're consumables
     * (medicines/supplies) and are bucketed as 'medicines' wholesale.
     * The visit_charges table likewise has no kind column, so we fall back
     * to the visit-level snapshot totals (fees_total / packages_price_total
     * / items_price_total) which the booking flow already populates.
     *
     * @return array<string, float>
     */
    protected function bucketGross(Visit $visit): array
    {
        $fees = round((float) ($visit->fees_total ?? 0), 3);
        $packages = round((float) ($visit->packages_price_total ?? 0), 3);
        $items = round((float) ($visit->items_price_total ?? 0), 3);

        $buckets = [];

        if ($fees > 0) {
            $buckets[InsuranceCoverageRule::KIND_CONSULTATION] = $fees;
        }
        if ($packages > 0) {
            $buckets[InsuranceCoverageRule::KIND_SERVICES] = $packages;
        }
        if ($items > 0) {
            $buckets[InsuranceCoverageRule::KIND_MEDICINES] = $items;
        }

        return $buckets;
    }

    /**
     * Find (and cache) the coverage rule for a given policy + kind.
     */
    protected function ruleFor(PatientInsurancePolicy $policy, string $kind): ?InsuranceCoverageRule
    {
        $planId = (int) ($policy->plan_id ?? 0);
        if ($planId <= 0) {
            return null;
        }

        $key = $planId.':'.$kind;
        if (array_key_exists($key, $this->ruleCache)) {
            return $this->ruleCache[$key];
        }

        $rule = InsuranceCoverageRule::query()
            ->where('plan_id', $planId)
            ->where('kind', $kind)
            ->first();

        return $this->ruleCache[$key] = $rule;
    }
}
