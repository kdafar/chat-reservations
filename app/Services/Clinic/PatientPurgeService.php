<?php

namespace App\Services\Clinic;

use App\Models\Patient;
use App\Models\PatientFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Permanently remove a patient and everything recorded for them — for test
 * and duplicate patients, where a soft delete would leave the visits, money
 * and journal entries behind in every report.
 *
 * preview() and purge() walk the same plan, so what the admin is shown is
 * exactly what goes. Everything runs in one transaction. Money the patient
 * paid is removed with its journal entries (the reports then simply no longer
 * include it); stock their visits used is put back on the shelf.
 *
 * Refuses (see blockers()) only where deleting would rewrite something that
 * already happened to someone else: a current admission, a doctor's share
 * already paid in a payroll run, or an insurer's payment already received.
 *
 * Works on the query builder, not models, so no global scope (clinic/branch)
 * can hide a row from the purge and no model event re-posts accounting.
 */
class PatientPurgeService
{
    /** Models whose rows can be the subject of activity-log or journal-entry links. */
    private const MORPH = [
        'patient' => 'App\\Models\\Patient',
        'visits' => 'App\\Models\\Visit',
        'bookings' => 'App\\Models\\Booking',
        'payments' => 'App\\Models\\VisitPayment',
        'charges' => 'App\\Models\\VisitCharge',
        'items' => 'App\\Models\\VisitItem',
        'packages' => 'App\\Models\\VisitPackage',
        'claims' => 'App\\Models\\Insurance\\InsuranceClaim',
        'claim_payments' => 'App\\Models\\Insurance\\InsuranceClaimPayment',
        'compensations' => 'App\\Models\\DoctorCompensationLedger',
        'movements' => 'App\\Models\\ClinicStockMovement',
        'stock_requests' => 'App\\Models\\VisitStockRequest',
        'lab_orders' => 'App\\Models\\Lab\\LabOrder',
        'files' => 'App\\Models\\PatientFile',
        'policies' => 'App\\Models\\Insurance\\PatientInsurancePolicy',
        'admissions' => 'App\\Models\\Inpatient\\Admission',
    ];

    /** @return array{patient: array, counts: array<string,int>, money: array, blockers: string[]} */
    public function preview(Patient $patient): array
    {
        $ids = $this->collect((int) $patient->id);

        return [
            'patient' => ['id' => $patient->id, 'name' => $patient->name],
            'counts' => collect($ids)->except(['journal_lines', 'activity'])->map(fn ($v) => count($v))
                ->put('journal_entries', count($ids['journal_entries']))
                ->put('activity', count($ids['activity']))
                ->filter()->all(),
            'money' => [
                'paid' => round((float) $this->sum('visit_payments', 'amount', $ids['payments'], ['status' => 'paid']), 3),
                'journal_debits' => round((float) $this->sum('journal_entry_lines', 'debit', $ids['journal_lines']), 3),
                'stock_units_returned' => round(abs((float) $this->sum('clinic_stock_movements', 'qty_change_base', $ids['movements'], ['type' => 'consume'])), 3),
            ],
            'blockers' => $this->blockers($ids),
        ];
    }

    /**
     * Delete it all. Returns the counts that were removed.
     *
     * @throws \RuntimeException when a blocker applies (nothing is deleted)
     */
    public function purge(Patient $patient, ?int $userId = null): array
    {
        return DB::transaction(function () use ($patient, $userId) {
            // Lock the patient row so two admins can't purge (or edit) it at once.
            DB::table('patients')->where('id', $patient->id)->lockForUpdate()->first();
            $ids = $this->collect((int) $patient->id);
            if ($blockers = $this->blockers($ids)) {
                throw new \RuntimeException(implode(' ', $blockers));
            }
            $counts = $this->preview($patient)['counts'];
            $files = $this->filePaths($ids['files']);

            // Stock the visits used goes back where it came from.
            foreach ($this->rows('clinic_stock_movements', $ids['movements']) as $mv) {
                if ((float) $mv->qty_change_base != 0.0 && $mv->clinic_item_stock_id) {
                    DB::table('clinic_item_stocks')->where('id', $mv->clinic_item_stock_id)
                        ->update(['qty_on_hand_base' => DB::raw('qty_on_hand_base - ('.(float) $mv->qty_change_base.')'), 'updated_at' => now()]);
                }
            }

            // Children before parents. Tables with ON DELETE CASCADE children
            // (journal lines, claim items, lab order items…) take those along.
            $this->del('journal_entries', $ids['journal_entries']);
            $this->del('insurance_claim_payments', $ids['claim_payments']);
            $this->del('insurance_claims', $ids['claims']);
            $this->del('insurance_preauthorizations', $ids['preauths']);
            $this->del('clinic_stock_movements', $ids['movements']);
            $this->del('visit_stock_requests', $ids['stock_requests']);
            $this->del('patient_files', $ids['files']);
            $this->del('lab_orders', $ids['lab_orders']);
            $this->del('doctor_compensation_ledgers', $ids['compensations']);
            foreach (['visit_documents', 'visit_events', 'queue_calls'] as $t) {
                if (Schema::hasTable($t) && $ids['visits']) {
                    DB::table($t)->whereIn('visit_id', $ids['visits'])->delete();
                }
            }
            $this->del('visit_payments', $ids['payments']);
            $this->del('visit_charges', $ids['charges']);
            $this->del('visit_items', $ids['items']);
            $this->del('visit_packages', $ids['packages']);
            $this->del('follow_up_plans', $ids['follow_ups']);
            $this->del('admissions', $ids['admissions']);
            $this->del('visits', $ids['visits']);
            $this->del('bookings', $ids['bookings']);
            $this->del('patient_insurance_policies', $ids['policies']);
            $this->del('activity_log', $ids['activity']);
            DB::table('patients')->where('id', $patient->id)->delete();

            // What was removed, never who: no name, phone or civil id is kept.
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->insert([
                    'log_name' => 'patient_purge', 'event' => 'purged',
                    'description' => 'Patient record permanently deleted with all related records',
                    'subject_type' => self::MORPH['patient'], 'subject_id' => $patient->id,
                    'causer_type' => $userId ? 'App\\Models\\User' : null, 'causer_id' => $userId,
                    'properties' => json_encode(['counts' => $counts]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // Files go last, after the rows are gone; a missing file is not an error.
            DB::afterCommit(function () use ($files) {
                foreach ($files as [$disk, $path]) {
                    try { $disk->delete($path); } catch (\Throwable $e) { Log::warning('patient purge: file not removed', ['path' => $path, 'error' => $e->getMessage()]); }
                }
            });

            return $counts;
        });
    }

    /** Every row id the purge would touch, by kind. */
    private function collect(int $patientId): array
    {
        $in = fn (string $table, string $col, array $vals) => ($vals && Schema::hasTable($table) && Schema::hasColumn($table, $col))
            ? DB::table($table)->whereIn($col, $vals)->pluck('id')->map(fn ($v) => (int) $v)->all() : [];
        $u = fn (array ...$lists) => array_values(array_unique(array_merge(...$lists)));
        $p = [$patientId];

        $visits = $in('visits', 'patient_id', $p);
        $bookings = $u($in('bookings', 'patient_id', $p),
            $visits && Schema::hasColumn('visits', 'booking_id') ? DB::table('visits')->whereIn('id', $visits)->whereNotNull('booking_id')->pluck('booking_id')->map(fn ($v) => (int) $v)->all() : []);
        $policies = $in('patient_insurance_policies', 'patient_id', $p);
        $payments = $in('visit_payments', 'visit_id', $visits);
        $claims = $u($in('insurance_claims', 'visit_id', $visits), $in('insurance_claims', 'patient_policy_id', $policies));
        $stockRequests = $in('visit_stock_requests', 'visit_id', $visits);
        $ids = [
            'patient' => $p,
            'visits' => $visits,
            'bookings' => $bookings,
            'policies' => $policies,
            'payments' => $payments,
            'charges' => $in('visit_charges', 'visit_id', $visits),
            'items' => $in('visit_items', 'visit_id', $visits),
            'packages' => $in('visit_packages', 'visit_id', $visits),
            'claims' => $claims,
            'claim_payments' => $in('insurance_claim_payments', 'claim_id', $claims),
            'preauths' => $u($in('insurance_preauthorizations', 'visit_id', $visits), $in('insurance_preauthorizations', 'patient_policy_id', $policies)),
            'stock_requests' => $stockRequests,
            'lab_orders' => $u($in('lab_orders', 'patient_id', $p), $in('lab_orders', 'visit_id', $visits)),
            'files' => $u($in('patient_files', 'patient_id', $p), $in('patient_files', 'visit_id', $visits)),
            'compensations' => $in('doctor_compensation_ledgers', 'visit_id', $visits),
            'follow_ups' => $u($in('follow_up_plans', 'patient_id', $p), $in('follow_up_plans', 'booking_id', $bookings)),
            'admissions' => $in('admissions', 'patient_id', $p),
        ];

        // Stock the visit used: movements linked to the visit or its lines / stock requests.
        $ids['movements'] = [];
        if (Schema::hasTable('clinic_stock_movements')) {
            $q = DB::table('clinic_stock_movements')->where(function ($w) use ($ids) {
                foreach (['visits', 'items', 'packages', 'stock_requests'] as $k) {
                    if ($ids[$k]) {
                        $w->orWhere(fn ($x) => $x->where('related_type', self::MORPH[$k])->whereIn('related_id', $ids[$k]));
                    }
                }
            });
            $ids['movements'] = collect(['visits', 'items', 'packages', 'stock_requests'])->contains(fn ($k) => $ids[$k])
                ? $q->pluck('id')->map(fn ($v) => (int) $v)->all() : [];
        }

        // Journal entries: posted for any of these rows, or carrying the patient on a line,
        // plus any reversal of those.
        $entries = collect();
        if (Schema::hasTable('journal_entries')) {
            foreach (['visits', 'payments', 'charges', 'items', 'packages', 'claims', 'claim_payments', 'compensations', 'movements'] as $k) {
                if ($ids[$k]) {
                    $entries = $entries->merge(DB::table('journal_entries')->where('source_type', self::MORPH[$k])->whereIn('source_id', $ids[$k])->pluck('id'));
                }
            }
            if (Schema::hasColumn('journal_entry_lines', 'patient_id')) {
                $entries = $entries->merge(DB::table('journal_entry_lines')->where('patient_id', $patientId)->pluck('journal_entry_id'));
            }
            $entries = $entries->map(fn ($v) => (int) $v)->unique();
            for ($i = 0; $i < 3 && $entries->isNotEmpty(); $i++) {   // reversals of reversals are rare; 3 levels is plenty
                $more = DB::table('journal_entries')->whereIn('reversal_of_id', $entries)->orWhereIn('reversed_by_id', $entries)->pluck('id')->map(fn ($v) => (int) $v);
                if ($more->diff($entries)->isEmpty()) {
                    break;
                }
                $entries = $entries->merge($more)->unique();
            }
        }
        $ids['journal_entries'] = $entries->values()->all();
        $ids['journal_lines'] = $entries->isNotEmpty() ? DB::table('journal_entry_lines')->whereIn('journal_entry_id', $entries)->pluck('id')->all() : [];

        // Activity-log rows about any of these records.
        $ids['activity'] = [];
        if (Schema::hasTable('activity_log')) {
            $ids['activity'] = DB::table('activity_log')->where(function ($w) use ($ids) {
                foreach (self::MORPH as $k => $class) {
                    if (! empty($ids[$k])) {
                        $w->orWhere(fn ($x) => $x->where('subject_type', $class)->whereIn('subject_id', $ids[$k]));
                    }
                }
            })->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        return $ids;
    }

    /** Reasons the purge must not run. Empty = safe to go. */
    private function blockers(array $ids): array
    {
        $out = [];
        if ($ids['admissions'] && Schema::hasColumn('admissions', 'discharged_at')
            && DB::table('admissions')->whereIn('id', $ids['admissions'])->whereNull('discharged_at')->exists()) {
            $out[] = 'The patient is currently admitted — discharge them first.';
        }
        if ($ids['compensations'] && DB::table('doctor_compensation_ledgers')->whereIn('id', $ids['compensations'])->whereNotNull('settled_payroll_run_id')->exists()) {
            $out[] = 'A doctor has already been paid for this patient\'s visits in a payroll run.';
        }
        if ($ids['claim_payments']) {
            $out[] = 'An insurer has already paid a claim for this patient.';
        }

        return $out;
    }

    private function del(string $table, array $ids): void
    {
        if ($ids && Schema::hasTable($table)) {
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table($table)->whereIn('id', $chunk)->delete();
            }
        }
    }

    private function rows(string $table, array $ids): Collection
    {
        return ($ids && Schema::hasTable($table)) ? DB::table($table)->whereIn('id', $ids)->get() : collect();
    }

    private function sum(string $table, string $col, array $ids, array $where = []): float
    {
        return ($ids && Schema::hasTable($table)) ? (float) DB::table($table)->whereIn('id', $ids)->where($where)->sum($col) : 0.0;
    }

    /** [disk, path] for each file, read before the rows go. */
    private function filePaths(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return PatientFile::withoutGlobalScopes()->withTrashed()->whereIn('id', $ids)->get()
            ->filter(fn ($f) => $f->file_path)
            ->map(fn ($f) => [$f->disk(), $f->file_path])->values()->all();
    }
}
