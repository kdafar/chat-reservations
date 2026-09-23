<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\Clinic\PatientPurgeService;
use Illuminate\Console\Command;

/**
 * Permanently delete a (test or duplicate) patient and everything recorded for
 * them. Shows what will go and asks first; the same service backs the admin's
 * "Delete patient" button in v2.
 */
class PurgePatient extends Command
{
    protected $signature = 'patients:purge {patient : Patient id} {--force : Skip the confirmation question}';

    protected $description = 'Permanently delete a patient with all their visits, bookings, payments, journal entries and files';

    public function handle(PatientPurgeService $svc): int
    {
        $patient = Patient::withoutGlobalScopes()->withTrashed()->find($this->argument('patient'));
        if (! $patient) {
            $this->error('No patient with that id.');

            return self::FAILURE;
        }

        $p = $svc->preview($patient);
        $this->info("Patient #{$patient->id}: {$patient->name}");
        $this->table(['Record', 'Count'], collect($p['counts'])->map(fn ($n, $k) => [str_replace('_', ' ', $k), $n])->values()->all());
        $this->line('Paid: '.number_format($p['money']['paid'], 3).' · journal debits: '.number_format($p['money']['journal_debits'], 3)
            .' · stock units returned: '.$p['money']['stock_units_returned']);
        if ($p['blockers']) {
            foreach ($p['blockers'] as $b) {
                $this->error($b);
            }

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Permanently delete ALL of this? It cannot be undone.')) {
            $this->line('Nothing deleted.');

            return self::SUCCESS;
        }

        $svc->purge($patient, null);
        $this->info('Deleted.');

        return self::SUCCESS;
    }
}
