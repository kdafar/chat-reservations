<?php

namespace Database\Seeders\Demo;

use App\Models\DoctorCompensationLedger;
use App\Models\Visit;
use App\Services\Clinic\DoctorCompensationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts the doctors on a treatment-based commission and re-syncs the ledger.
 *
 * The profiles were set to `fees_only`, which pays a percentage of the
 * consultation fee alone. That was survivable while treatments were billed as
 * free-text charges (everything landed in fees_total), but once treatments
 * became costed line items the commission base collapsed to the consult fee
 * and the group's net margin shot up past 50%.
 *
 * `net_profit` bills the doctor's share against the visit's profit — treatment
 * revenue less the product consumed — which is how an aesthetic clinic actually
 * pays an injector. Rates land around 40%, the normal market share.
 *
 * Re-syncs through DoctorCompensationService so the ledger's accounting
 * observer re-posts each commission entry to the GL.
 */
class DemoDoctorCompensationSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $profiles = DB::table('doctor_compensation_profiles')
            ->where('type', 'percentage')->where('is_active', 1)->get(['id', 'doctor_id']);

        foreach ($profiles as $p) {
            DB::table('doctor_compensation_profiles')->where('id', $p->id)->update([
                'basis' => 'net_profit',
                // Spread 36–44% so the doctor-performance table isn't uniform.
                'percentage_rate' => 36 + (($p->doctor_id % 5) * 2),
            ]);
        }
        $this->command?->info("DemoDoctorCompensationSeeder: moved {$profiles->count()} profiles to a net-profit basis.");

        $sync = app(DoctorCompensationService::class);
        $done = 0;

        Visit::query()->withoutGlobalScopes()
            ->where('status', Visit::STATUS_COMPLETED)
            ->whereNotNull('computed_at')
            ->orderBy('id')
            ->chunkById(300, function ($visits) use ($sync, &$done) {
                foreach ($visits as $visit) {
                    // Keep the ledger row (and its journal entry) on the visit's date.
                    Carbon::setTestNow(Carbon::parse($visit->computed_at));
                    try {
                        $sync->sync($visit, 0);
                    } catch (\Throwable $e) {
                        // a visit without an active profile simply has no cut
                    }
                    Carbon::setTestNow();

                    $done++;
                    if ($done % 750 === 0) {
                        $this->command?->info("  {$done} visits re-synced");
                    }
                }
            });

        Carbon::setTestNow();
        $total = (float) DoctorCompensationLedger::query()->sum('doctor_cut_amount');
        $this->command?->info("DemoDoctorCompensationSeeder: re-synced {$done} visits; commission now ".number_format($total, 0).' KWD.');

        $this->repostLedgerToGl();
    }

    /**
     * Rebuild the commission side of the GL from the ledger.
     *
     * recordDoctorCompensation() reverses and re-posts when an amount changes,
     * but journal_entries is unique on (source_type, source_id, status) — after
     * a bulk re-rate the re-post collides with the row it just reversed and the
     * service swallows the error, leaving the expense understated. Wiping every
     * commission entry and posting once per ledger row avoids the collision
     * entirely and is the only way to get a ledger-accurate 5130 balance.
     */
    protected function repostLedgerToGl(): void
    {
        $sourced = DB::table('journal_entries')
            ->where('source_type', DoctorCompensationLedger::class)->pluck('id');
        $reversals = DB::table('journal_entries')
            ->whereIn('reversal_of_id', $sourced)->pluck('id');
        $all = $sourced->merge($reversals)->unique()->values()->all();

        foreach (array_chunk($all, 1000) as $chunk) {
            DB::table('journal_entry_lines')->whereIn('journal_entry_id', $chunk)->delete();
            DB::table('journal_entries')->whereIn('id', $chunk)->delete();
        }
        $this->command?->info('  cleared '.count($all).' stale commission journal entries');

        $accounting = app(\App\Services\Accounting\AccountingService::class);
        $posted = 0;

        DoctorCompensationLedger::query()->where('doctor_cut_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($ledgers) use ($accounting, &$posted) {
                foreach ($ledgers as $ledger) {
                    Carbon::setTestNow(Carbon::parse($ledger->created_at));
                    if ($accounting->recordDoctorCompensation($ledger)) {
                        $posted++;
                    }
                    Carbon::setTestNow();
                }
            });

        Carbon::setTestNow();
        $this->command?->info("  re-posted {$posted} commission entries to the GL");
    }
}
