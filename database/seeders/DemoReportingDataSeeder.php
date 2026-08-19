<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoClaimLifecycleSeeder;
use Database\Seeders\Demo\DemoClinicalVolumeSeeder;
use Database\Seeders\Demo\DemoDiscountSeeder;
use Database\Seeders\Demo\DemoDoctorCompensationSeeder;
use Database\Seeders\Demo\DemoFollowUpOutcomeSeeder;
use Database\Seeders\Demo\DemoFinanceSeeder;
use Database\Seeders\Demo\DemoGlRebuildSeeder;
use Database\Seeders\Demo\DemoHrPayrollSeeder;
use Database\Seeders\Demo\DemoInpatientSeeder;
use Database\Seeders\Demo\DemoOperationsSeeder;
use Database\Seeders\Demo\DemoPackageSalesSeeder;
use Database\Seeders\Demo\DemoRepairSeeder;
use Database\Seeders\Demo\DemoTreatmentCostingSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills the modules whose tables are empty so every report screen has data to
 * draw. Built for demos and pitch walkthroughs — it writes plausible history
 * across the last few months rather than a single snapshot.
 *
 *   php artisan db:seed --class=DemoReportingDataSeeder
 *
 * Each sub-seeder is idempotent at the module level: if its main table already
 * has rows it skips rather than duplicating, so re-running is safe.
 *
 * Order matters:
 *   - the repair pass runs first, before anything reads a branch relation;
 *   - finance opens the accounting periods payroll and depreciation post into;
 *   - costing has to precede compensation, because the doctors are paid on the
 *     visit's profit and that is only real once treatments carry a cost;
 *   - the GL rebuild runs last, once every source document is final, and
 *     asserts the ledger reconciles to the visits.
 *
 * Expect this to take roughly an hour — the clinical history is written through
 * the real service layer (costing, commission, revenue accrual) rather than
 * bulk-inserted, which is what keeps the six accounting reports correct.
 */
class DemoReportingDataSeeder extends Seeder
{
    public function run(): void
    {
        // This run writes hundreds of thousands of rows; keeping the query log
        // and Telescope on exhausts memory partway through.
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $this->call([
            DemoRepairSeeder::class,
            DemoClinicalVolumeSeeder::class,
            DemoPackageSalesSeeder::class,
            DemoTreatmentCostingSeeder::class,
            DemoDiscountSeeder::class,
            DemoDoctorCompensationSeeder::class,
            DemoInpatientSeeder::class,
            DemoFinanceSeeder::class,
            DemoHrPayrollSeeder::class,
            DemoOperationsSeeder::class,
            // Outcomes for the workflows the app auto-creates but nobody closed:
            // drafted insurance claims and auto-booked follow-ups. Without these
            // the insurance and appointment reports read as 0% conversion.
            DemoClaimLifecycleSeeder::class,
            DemoFollowUpOutcomeSeeder::class,
            DemoGlRebuildSeeder::class,
        ]);
    }
}
