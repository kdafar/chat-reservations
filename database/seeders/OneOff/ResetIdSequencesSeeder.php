<?php

namespace Database\Seeders\OneOff;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ONE-OFF — resets id counters left inflated by demo data that was later wiped.
 *
 *     php artisan db:seed --class="Database\Seeders\OneOff\ResetIdSequencesSeeder"
 *
 * This install was seeded with a large demo dataset (thousands of visits,
 * bookings and journal entries) and then cleaned. Deleting rows does NOT reset
 * a table's AUTO_INCREMENT, so the first REAL patient was created as #906 and
 * the clinic sees "Patient · #906" on a brand-new account.
 *
 * Two separate jobs:
 *
 *   1. Renumber the handful of real patients to start at 1, updating every
 *      row that points at them in the same transaction.
 *   2. Reset AUTO_INCREMENT to 1 on tables that are EMPTY, so the next real
 *      record in each starts at 1. Empty means there is nothing to renumber
 *      and nothing to point at them, which is what makes it safe.
 *
 * Tables that still hold rows are left alone apart from patients — renumbering
 * live data is only safe when every reference is known, and it is not worth the
 * risk for a cosmetic id. Append-only logs (activity_log, telescope_entries)
 * are never renumbered.
 *
 * TAKE A DATABASE BACKUP FIRST. This rewrites primary keys.
 *
 * Aborts rather than guessing if the data does not look like it expects.
 */
class ResetIdSequencesSeeder extends Seeder
{
    /**
     * Everything that can point at a patient id. Verified against
     * information_schema (FKs) plus a scan for any column named *patient_id*,
     * which is how bookings.patient_id was found — it has no FK constraint.
     */
    private const PATIENT_REFS = [
        ['table' => 'visits', 'column' => 'patient_id'],
        ['table' => 'bookings', 'column' => 'patient_id'],
        ['table' => 'patient_files', 'column' => 'patient_id'],
        ['table' => 'patient_insurance_policies', 'column' => 'patient_id'],
        ['table' => 'admissions', 'column' => 'patient_id'],
        ['table' => 'journal_entry_lines', 'column' => 'patient_id'],
    ];

    /**
     * Everything that can point at a booking id. FK scan plus a sweep for any
     * column named *booking_id*, the same way the patient list was built.
     *
     * bookings.booking_code is NOT touched -- it is the reference the patient
     * actually holds, and it is independent of the numeric id.
     */
    private const BOOKING_REFS = [
        ['table' => 'visits', 'column' => 'booking_id'],
    ];

    /** Renumbering more than this many rows is not a cosmetic fix any more. */
    private const MAX_PATIENTS = 50;

    private const MAX_BOOKINGS = 50;

    /** Logs are append-only history; their ids are meaningless but stable. */
    private const NEVER_RESET = ['activity_log', 'telescope_entries', 'migrations'];

    public function run(): void
    {
        $this->command?->warn('This rewrites primary keys. Ensure you have a database backup.');

        $this->renumberPatients();
        $this->renumberBookings();
        $this->resetEmptyTables();
    }

    private function renumberPatients(): void
    {
        $patients = DB::table('patients')->orderBy('id')->get(['id']);

        if ($patients->isEmpty()) {
            $this->command?->info('No patients — nothing to renumber.');
            $this->setAutoIncrement('patients', 1);

            return;
        }

        if ($patients->count() > self::MAX_PATIENTS) {
            $this->command?->error(
                'Refusing to renumber '.$patients->count().' patients (limit '.self::MAX_PATIENTS.'). '
                .'Too much live data for a cosmetic id change.'
            );

            return;
        }

        // old id => new id, sequential from 1 in creation order.
        $map = [];
        $next = 1;
        foreach ($patients as $p) {
            $map[(int) $p->id] = $next++;
        }

        if ($map === array_combine(array_keys($map), array_keys($map))) {
            $this->command?->info('Patient ids already start at 1 — nothing to do.');
            $this->setAutoIncrement('patients', $next);

            return;
        }

        // Every target id must be free, or the update would collide.
        $targets = array_values($map);
        $occupied = DB::table('patients')
            ->whereIn('id', $targets)
            ->whereNotIn('id', array_keys($map))
            ->count();

        if ($occupied > 0) {
            $this->command?->error('Target ids are not free — aborting.');

            return;
        }

        DB::transaction(function () use ($map) {
            foreach ($map as $old => $new) {
                DB::table('patients')->where('id', $old)->update(['id' => $new]);

                foreach (self::PATIENT_REFS as $ref) {
                    if (! DB::getSchemaBuilder()->hasTable($ref['table'])) {
                        continue;
                    }

                    DB::table($ref['table'])
                        ->where($ref['column'], $old)
                        ->update([$ref['column'] => $new]);
                }

                // Spatie activity log stores the id polymorphically.
                DB::table('activity_log')
                    ->where('subject_type', 'like', '%\\Patient')
                    ->where('subject_id', $old)
                    ->update(['subject_id' => $new]);
            }
        });

        $this->setAutoIncrement('patients', max($map) + 1);

        foreach ($map as $old => $new) {
            $this->command?->info("  patient #{$old} -> #{$new}");
        }
    }

    private function renumberBookings(): void
    {
        $bookings = DB::table('bookings')->orderBy('id')->get(['id']);

        if ($bookings->isEmpty()) {
            $this->command?->info('No bookings — nothing to renumber.');
            $this->setAutoIncrement('bookings', 1);

            return;
        }

        if ($bookings->count() > self::MAX_BOOKINGS) {
            $this->command?->error(
                'Refusing to renumber '.$bookings->count().' bookings (limit '.self::MAX_BOOKINGS.').'
            );

            return;
        }

        $map = [];
        $next = 1;
        foreach ($bookings as $b) {
            $map[(int) $b->id] = $next++;
        }

        if ($map === array_combine(array_keys($map), array_keys($map))) {
            $this->command?->info('Booking ids already start at 1 — nothing to do.');
            $this->setAutoIncrement('bookings', $next);

            return;
        }

        $occupied = DB::table('bookings')
            ->whereIn('id', array_values($map))
            ->whereNotIn('id', array_keys($map))
            ->count();

        if ($occupied > 0) {
            $this->command?->error('Target booking ids are not free — aborting.');

            return;
        }

        DB::transaction(function () use ($map) {
            foreach ($map as $old => $new) {
                DB::table('bookings')->where('id', $old)->update(['id' => $new]);

                foreach (self::BOOKING_REFS as $ref) {
                    if (! DB::getSchemaBuilder()->hasTable($ref['table'])) {
                        continue;
                    }

                    DB::table($ref['table'])
                        ->where($ref['column'], $old)
                        ->update([$ref['column'] => $new]);
                }

                DB::table('activity_log')
                    ->where('subject_type', 'like', '%\\Booking')
                    ->where('subject_id', $old)
                    ->update(['subject_id' => $new]);
            }
        });

        $this->setAutoIncrement('bookings', max($map) + 1);

        foreach ($map as $old => $new) {
            $this->command?->info("  booking #{$old} -> #{$new}");
        }
    }

    /**
     * An empty table has nothing to renumber and nothing referencing it, so
     * restarting its counter at 1 cannot lose or orphan anything.
     */
    private function resetEmptyTables(): void
    {
        $db = DB::getDatabaseName();

        $tables = DB::select(
            'SELECT TABLE_NAME t, AUTO_INCREMENT ai FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND AUTO_INCREMENT IS NOT NULL AND AUTO_INCREMENT > 1',
            [$db]
        );

        $reset = 0;

        foreach ($tables as $t) {
            if (in_array($t->t, self::NEVER_RESET, true)) {
                continue;
            }

            if (DB::table($t->t)->count() > 0) {
                continue;
            }

            $this->setAutoIncrement($t->t, 1);
            $reset++;
        }

        $this->command?->info("Reset {$reset} empty table(s) to start at 1.");
    }

    private function setAutoIncrement(string $table, int $value): void
    {
        // Table names come from information_schema / this class's constants,
        // never from user input, but quote them anyway.
        DB::statement('ALTER TABLE `'.str_replace('`', '', $table).'` AUTO_INCREMENT = '.(int) $value);
    }
}
