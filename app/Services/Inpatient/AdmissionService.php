<?php

namespace App\Services\Inpatient;

use App\Models\Doctor;
use App\Models\Inpatient\Admission;
use App\Models\Inpatient\AdmissionCharge;
use App\Models\Inpatient\Bed;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCharge;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestrates admit → (transfer*) → discharge. Bed mechanics live in
 * BedAssignmentService; this class owns the lifecycle, code generation,
 * and the discharge-bill rollup into a final Visit.
 */
class AdmissionService
{
    public function __construct(protected BedAssignmentService $beds) {}

    /**
     * Create a new admission. If $initialBed is provided, immediately
     * assigns the patient to that bed (one transaction).
     *
     * @param array{patient_id:int,admitting_doctor_id:int,branch_id:int,partner_id:int,admission_reason:string,admitting_visit_id?:?int,diagnosis?:?string,expected_discharge_at?:?\DateTimeInterface,admitted_at?:?\DateTimeInterface} $data
     */
    public function admit(array $data, ?Bed $initialBed = null, ?User $user = null): Admission
    {
        return DB::transaction(function () use ($data, $initialBed, $user) {
            $patient = Patient::query()->findOrFail($data['patient_id']);
            $doctor = Doctor::query()->findOrFail($data['admitting_doctor_id']);

            // Defensive: refuse a second active admission for the same patient,
            // anywhere — withoutGlobalScopes() so an active admission in another
            // branch of the clinic still blocks a duplicate.
            $existing = Admission::query()
                ->withoutGlobalScopes()
                ->where('patient_id', $patient->id)
                ->where('status', Admission::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                throw new RuntimeException("Patient {$patient->name} already has an active admission (#{$existing->admission_code}).");
            }

            $admission = Admission::create([
                'partner_id' => $data['partner_id'],
                'branch_id' => $data['branch_id'],
                'patient_id' => $patient->id,
                'admitting_doctor_id' => $doctor->id,
                'admitting_visit_id' => $data['admitting_visit_id'] ?? null,
                'admission_code' => $this->nextAdmissionCode(),
                'admitted_at' => $data['admitted_at'] ?? now(),
                'expected_discharge_at' => $data['expected_discharge_at'] ?? null,
                'admission_reason' => $data['admission_reason'],
                'diagnosis' => $data['diagnosis'] ?? null,
                'status' => Admission::STATUS_ACTIVE,
            ]);

            if ($initialBed) {
                $this->beds->assign($admission, $initialBed, $user, 'Initial admission');
            }

            return $admission->fresh(['currentBedStay.bed.ward']);
        });
    }

    /**
     * Discharge an active admission:
     *   - release the current bed
     *   - sum all bed-day charges
     *   - create a final Visit (status awaiting_payment) with one
     *     VisitCharge per bed-day so reception collects via the
     *     existing payment flow
     *   - flip admission status + stamp discharged_at / by / summary
     *
     * Returns ['admission' => ..., 'final_visit' => ..., 'total' => float].
     */
    public function discharge(Admission $admission, User $user, string $summary, string $finalStatus = Admission::STATUS_DISCHARGED): array
    {
        if (! in_array($finalStatus, [
            Admission::STATUS_DISCHARGED,
            Admission::STATUS_LAMA,
            Admission::STATUS_EXPIRED,
            Admission::STATUS_TRANSFERRED_OUT,
        ], true)) {
            throw new RuntimeException("Invalid discharge status: {$finalStatus}");
        }

        return DB::transaction(function () use ($admission, $user, $summary, $finalStatus) {
            $admission = Admission::query()->whereKey($admission->id)->lockForUpdate()->first();
            if (! $admission || $admission->status !== Admission::STATUS_ACTIVE) {
                throw new RuntimeException('Admission is not active.');
            }

            $this->beds->release($admission, $user, 'Discharge');

            // Bundle all bed-day charges into one Visit row that goes
            // through the same VisitPayment / insurance / accounting flow.
            $charges = AdmissionCharge::query()
                ->where('admission_id', $admission->id)
                ->orderBy('charge_date')
                ->get();

            $total = (float) $charges->sum('amount');

            $visit = Visit::create([
                'patient_id' => $admission->patient_id,
                'doctor_id' => $admission->admitting_doctor_id,
                'branch_id' => $admission->branch_id,
                'status' => Visit::STATUS_AWAITING_PAYMENT,
                'queued_at' => now(),
                'checked_in_at' => $admission->admitted_at,
                'service_started_at' => $admission->admitted_at,
                'notes' => "Inpatient discharge bill for admission {$admission->admission_code}",
                'fees_total' => $total,
            ]);

            foreach ($charges as $c) {
                VisitCharge::create([
                    'visit_id' => $visit->id,
                    'branch_id' => $admission->branch_id,
                    'label' => $c->description.' ('.$c->charge_date->toDateString().')',
                    'qty' => 1,
                    'unit_price_snapshot' => $c->amount,
                    'line_total' => $c->amount,
                    'added_by_user_id' => $user->id,
                ]);
            }

            $admission->update([
                'status' => $finalStatus,
                'discharged_at' => now(),
                'discharged_by_user_id' => $user->id,
                'discharge_summary' => $summary,
                'final_visit_id' => $visit->id,
            ]);

            return [
                'admission' => $admission->fresh(),
                'final_visit' => $visit->fresh(),
                'total' => $total,
            ];
        });
    }

    /**
     * Generate the next ADM code for the current year. Race-safe via the
     * column's unique constraint — caller retries on collision.
     */
    protected function nextAdmissionCode(): string
    {
        $year = now()->year;

        // admission_code is GLOBALLY unique, so the sequence must be computed
        // across every clinic/branch — withoutGlobalScopes() drops the
        // BelongsToBranchScope that would otherwise count only the current
        // user's branch and regenerate an already-taken code. Max-based (not
        // count-based) so deletions never cause a code to be reused.
        $last = Admission::query()
            ->withoutGlobalScopes()
            ->where('admission_code', 'like', "ADM-{$year}-%")
            ->orderByDesc('admission_code')
            ->lockForUpdate()
            ->value('admission_code');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('ADM-%d-%05d', $year, $seq);
    }
}
