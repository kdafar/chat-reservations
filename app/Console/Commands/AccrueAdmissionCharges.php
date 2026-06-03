<?php

namespace App\Console\Commands;

use App\Models\Inpatient\Admission;
use App\Models\Inpatient\AdmissionBedStay;
use App\Models\Inpatient\AdmissionCharge;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly cron: for each active admission, create the bed-day charge
 * for the previous calendar day if not already billed. Idempotent — the
 * (admission_id, charge_date, source=bed_day) unique index stops doubles.
 *
 * "Billing the night they slept" rule: we bill the stay that was open at
 * end-of-target-day. So a patient transferred at 14:00 from ICU to General
 * Ward is billed for General Ward that night (where they actually slept).
 * Discharge day is NOT billed.
 */
class AccrueAdmissionCharges extends Command
{
    protected $signature = 'clinic:accrue-admission-charges
                            {--date= : Target date YYYY-MM-DD (default: yesterday)}
                            {--dry-run : Log without writing}';

    protected $description = 'Generate per-day bed charges for active admissions. Idempotent. Runs nightly via schedule.';

    public function handle(): int
    {
        $target = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->subDay()->startOfDay();
        $endOfTarget = $target->copy()->endOfDay();
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Accruing bed-day charges for {$target->toDateString()} dry_run=".($dryRun ? 'yes' : 'no'));

        // Stays that were OPEN at end-of-target — i.e. patient slept in
        // that bed that night. assigned_at <= EOD AND (released_at IS NULL
        // OR released_at > EOD).
        $stays = AdmissionBedStay::query()
            ->with('admission')
            ->where('assigned_at', '<=', $endOfTarget)
            ->where(function ($q) use ($endOfTarget) {
                $q->whereNull('released_at')
                    ->orWhere('released_at', '>', $endOfTarget);
            })
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($stays as $stay) {
            $admission = $stay->admission;
            if (! $admission) {
                $skipped++;
                continue;
            }
            // Don't bill the discharge day. If admission was discharged
            // ON the target day, skip (discharged_at <= EOD but > start).
            if ($admission->discharged_at && $admission->discharged_at->lte($endOfTarget)) {
                $skipped++;
                continue;
            }

            // Idempotency: unique key (admission_id, charge_date, source).
            $exists = AdmissionCharge::query()
                ->where('admission_id', $admission->id)
                ->whereDate('charge_date', $target)
                ->where('source', AdmissionCharge::SOURCE_BED_DAY)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  would charge admission#{$admission->id} ({$admission->admission_code}) bed#{$stay->bed_id} amount={$stay->daily_rate}");
                $created++;
                continue;
            }

            try {
                DB::transaction(function () use ($admission, $stay, $target) {
                    AdmissionCharge::create([
                        'admission_id' => $admission->id,
                        'bed_stay_id' => $stay->id,
                        'charge_date' => $target->toDateString(),
                        'amount' => $stay->daily_rate,
                        'description' => 'Bed day',
                        'source' => AdmissionCharge::SOURCE_BED_DAY,
                    ]);
                });
                $created++;
                $this->line("  charged admission#{$admission->id} ({$admission->admission_code}) amount={$stay->daily_rate}");
            } catch (\Throwable $e) {
                // Unique-constraint race: another runner beat us — that's fine.
                $skipped++;
                \Log::info('Bed-day accrual race avoided', ['admission' => $admission->id, 'date' => $target->toDateString()]);
            }
        }

        $this->info("Done. created={$created} skipped={$skipped} stays_checked=".$stays->count());
        return self::SUCCESS;
    }
}
