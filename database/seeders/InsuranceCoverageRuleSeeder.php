<?php

namespace Database\Seeders;

use App\Models\Insurance\InsuranceCoverageRule;
use App\Models\Insurance\InsurancePlan;
use Illuminate\Database\Seeder;

/**
 * Seeds 4 coverage rules (consultation, services, medicines, other) for every plan.
 *
 * Idempotent — upserts by `(plan_id, kind)`.
 *
 * Gold tier is more generous (high percentages, higher caps).
 * Silver tier uses a fixed copay for consultations and lower percentages elsewhere.
 */
class InsuranceCoverageRuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding insurance coverage rules...');

        $rulesByTier = [
            'gold' => [
                [
                    'kind' => 'consultation',
                    'coverage_type' => 'percentage',
                    'coverage_value' => 100,
                    'max_per_visit' => null,
                    'max_annual' => null,
                    'requires_preauth' => false,
                    'notes' => 'Full consultation coverage on Gold tier.',
                ],
                [
                    'kind' => 'services',
                    'coverage_type' => 'percentage',
                    'coverage_value' => 90,
                    'max_per_visit' => 200.000,
                    'max_annual' => 5000.000,
                    'requires_preauth' => true,
                    'notes' => 'Procedures/services 90% covered, pre-auth required.',
                ],
                [
                    'kind' => 'medicines',
                    'coverage_type' => 'percentage',
                    'coverage_value' => 80,
                    'max_per_visit' => 50.000,
                    'max_annual' => 1500.000,
                    'requires_preauth' => false,
                    'notes' => 'Pharmacy items 80% covered up to annual cap.',
                ],
                [
                    'kind' => 'other',
                    'coverage_type' => 'percentage',
                    'coverage_value' => 70,
                    'max_per_visit' => 100.000,
                    'max_annual' => 2000.000,
                    'requires_preauth' => false,
                    'notes' => 'Miscellaneous items 70% covered.',
                ],
            ],
            'silver' => [
                [
                    'kind' => 'consultation',
                    'coverage_type' => 'copay_amount',
                    'coverage_value' => 2.000,
                    'max_per_visit' => null,
                    'max_annual' => null,
                    'requires_preauth' => false,
                    'notes' => 'Patient pays 2 KWD copay; insurer covers the rest.',
                ],
                [
                    'kind' => 'services',
                    'coverage_type' => 'percentage',
                    'coverage_value' => 70,
                    'max_per_visit' => 150.000,
                    'max_annual' => 3000.000,
                    'requires_preauth' => true,
                    'notes' => 'Procedures/services 70% covered, pre-auth required.',
                ],
                [
                    'kind' => 'medicines',
                    'coverage_type' => 'percentage',
                    'coverage_value' => 60,
                    'max_per_visit' => 30.000,
                    'max_annual' => 800.000,
                    'requires_preauth' => false,
                    'notes' => 'Pharmacy items 60% covered.',
                ],
                [
                    'kind' => 'other',
                    'coverage_type' => 'percentage',
                    'coverage_value' => 50,
                    'max_per_visit' => 80.000,
                    'max_annual' => 1500.000,
                    'requires_preauth' => false,
                    'notes' => 'Miscellaneous items 50% covered.',
                ],
            ],
        ];

        $created = 0;

        foreach (InsurancePlan::query()->get() as $plan) {
            $tier = $plan->tier;
            if (! isset($rulesByTier[$tier])) {
                $this->command->warn("Plan {$plan->code} has unknown tier '{$tier}' — skipping.");
                continue;
            }

            foreach ($rulesByTier[$tier] as $rule) {
                InsuranceCoverageRule::updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'kind' => $rule['kind'],
                    ],
                    [
                        'coverage_type' => $rule['coverage_type'],
                        'coverage_value' => $rule['coverage_value'],
                        'max_per_visit' => $rule['max_per_visit'],
                        'max_annual' => $rule['max_annual'],
                        'requires_preauth' => $rule['requires_preauth'],
                        'notes' => $rule['notes'],
                        'meta' => [
                            'tier' => $tier,
                            'plan_code' => $plan->code,
                        ],
                    ]
                );
                $created++;
            }
        }

        $this->command->info("Seeded {$created} coverage rules across ".InsurancePlan::count().' plans.');
    }
}
