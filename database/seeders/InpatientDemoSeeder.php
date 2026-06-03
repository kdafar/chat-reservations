<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inpatient\Bed;
use App\Models\Inpatient\Ward;
use Illuminate\Database\Seeder;

/**
 * Demo wards + beds for the inpatient module. Idempotent: seeds one
 * ward of each type per existing branch, with a handful of beds each.
 * Skips if wards already exist for the branch.
 */
class InpatientDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()->get();
        if ($branches->isEmpty()) {
            $this->command?->warn('No branches found — skipping inpatient seed.');
            return;
        }

        $blueprint = [
            ['name' => 'General Ward A', 'code' => 'GEN-A', 'type' => Ward::TYPE_GENERAL, 'rate' => 25.000, 'beds' => 6],
            ['name' => 'ICU',             'code' => 'ICU',   'type' => Ward::TYPE_ICU,     'rate' => 120.000, 'beds' => 4],
            ['name' => 'Pediatric Ward',  'code' => 'PED',   'type' => Ward::TYPE_PEDIATRIC, 'rate' => 35.000, 'beds' => 4],
            ['name' => 'VIP Suites',      'code' => 'VIP',   'type' => Ward::TYPE_VIP,     'rate' => 200.000, 'beds' => 2],
        ];

        foreach ($branches as $branch) {
            if (Ward::query()->where('branch_id', $branch->id)->exists()) {
                $this->command?->line("  branch#{$branch->id}: wards already exist, skipping");
                continue;
            }

            foreach ($blueprint as $w) {
                $ward = Ward::create([
                    'partner_id' => $branch->partner_id,
                    'branch_id' => $branch->id,
                    'name' => $w['name'],
                    'code' => $w['code'],
                    'ward_type' => $w['type'],
                    'daily_rate' => $w['rate'],
                ]);

                for ($i = 1; $i <= $w['beds']; $i++) {
                    Bed::create([
                        'ward_id' => $ward->id,
                        'branch_id' => $branch->id,
                        'code' => $w['code'].'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'status' => Bed::STATUS_AVAILABLE,
                    ]);
                }
                $this->command?->line("  branch#{$branch->id}: created {$ward->name} with {$w['beds']} beds");
            }
        }
    }
}
