<?php

namespace Database\Seeders;

use App\Models\ClinicItem;
use Illuminate\Database\Seeder;

/**
 * Seeds the real EVA Medical treatment catalogue as global (all-clinic)
 * clinic_items of type 'service'.
 *
 * Source data: database/seeders/data/eva_treatments.php (parsed from the
 * EVA "Treatment Cost Breakdown" workbook). The workbook carries an
 * activity-based TOTAL COST but no selling price, so default_price is set
 * equal to default_cost — staff set real prices in the admin afterwards.
 *
 * The provider/category/equipment metadata from the workbook is kept in the
 * name only loosely (category is part of EVA's internal taxonomy and has no
 * column on clinic_items); the financially-meaningful field — cost — is loaded.
 */
class EvaTreatmentsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = require database_path('seeders/data/eva_treatments.php');
        $this->command?->info('Seeding '.count($rows).' EVA treatments...');

        $now = now();
        $created = 0;

        foreach ($rows as $r) {
            $cost = round((float) $r['cost'], 3);

            ClinicItem::create([
                'partner_id' => null,   // global — visible to every clinic
                'branch_id' => null,
                'name' => ['en' => $r['en'], 'ar' => $r['ar']],
                'type' => 'service',
                'is_stockable' => false,
                'stock_unit' => null,
                'usage_unit' => null,
                'conversion_factor' => 1,
                'consume_step' => 1,
                'is_billable' => true,
                'default_cost' => $cost,
                'default_price' => $cost, // price = cost (placeholder)
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created++;
        }

        $this->command?->info("Seeded {$created} treatments (price = cost).");
    }
}
