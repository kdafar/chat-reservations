<?php

namespace Database\Seeders;

use App\Models\Insurance\InsurancePlan;
use App\Models\Insurance\Insurer;
use Illuminate\Database\Seeder;

/**
 * Seeds a Gold and Silver insurance plan for every seeded insurer.
 *
 * Idempotent — upserts by `(insurer_id, code)`.
 * Plan code format: `{INSURER_CODE}-GOLD` / `{INSURER_CODE}-SILVER`.
 */
class InsurancePlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding insurance plans (Gold/Silver per insurer)...');

        $tiers = [
            'gold' => [
                'name' => 'Gold',
                'name_ar' => 'ذهبي',
                'suffix' => 'GOLD',
                'notes' => 'Premium tier — broad coverage, lower out-of-pocket.',
            ],
            'silver' => [
                'name' => 'Silver',
                'name_ar' => 'فضي',
                'suffix' => 'SILVER',
                'notes' => 'Basic tier — essential coverage at lower premium.',
            ],
        ];

        $created = 0;

        foreach (Insurer::query()->get() as $insurer) {
            foreach ($tiers as $tier => $cfg) {
                $code = $insurer->code.'-'.$cfg['suffix'];

                InsurancePlan::updateOrCreate(
                    [
                        'insurer_id' => $insurer->id,
                        'code' => $code,
                    ],
                    [
                        'name' => $insurer->name.' '.$cfg['name'],
                        'name_ar' => ($insurer->name_ar ?: $insurer->name).' - '.$cfg['name_ar'],
                        'tier' => $tier,
                        'effective_from' => '2026-01-01',
                        'effective_until' => null,
                        'is_active' => true,
                        'notes' => $cfg['notes'],
                        'meta' => [
                            'tier' => $tier,
                            'insurer_code' => $insurer->code,
                        ],
                    ]
                );
                $created++;
            }
        }

        $this->command->info("Seeded {$created} insurance plans across ".Insurer::count().' insurers.');
    }
}
