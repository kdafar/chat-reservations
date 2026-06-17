<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * One-shot loader that replaces the demo/test data with the real EVA Medical
 * data set:
 *
 *   1. EvaCleanSlateSeeder         — wipe demo transactions + old catalogue + COA
 *   2. AccountingChartOfAccountsSeeder — real bilingual EVA chart of accounts
 *   3. EvaTreatmentsSeeder         — 84 real treatments (price = cost)
 *   4. EvaInventorySeeder          — real consumables + opening stock + vendors
 *
 * Destructive (drops all demo transactions). Pre-launch use:
 *
 *   php artisan db:seed --class=EvaRealDataSeeder
 */
class EvaRealDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EvaCleanSlateSeeder::class,
            AccountingChartOfAccountsSeeder::class,
            PostingAccountMapSeeder::class,
            EvaTreatmentsSeeder::class,
            EvaInventorySeeder::class,
        ]);

        $this->command?->info('EVA real data load complete.');
    }
}
