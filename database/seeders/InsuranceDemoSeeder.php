<?php

namespace Database\Seeders;

use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\InsurancePlan;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\Insurance\InsuranceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Wires demo insurance data on top of the basic insurance seeders
 * (InsurerSeeder + InsurancePlanSeeder + InsuranceCoverageRuleSeeder).
 *
 * Picks up to 3 patients seeded by ClinicFreshMonthSeeder and attaches
 * policies to them, then drafts claims off any visits that already have
 * VisitCharge rows. Walks a handful of claims through the state machine
 * to demonstrate the full lifecycle (draft → submitted → under_review →
 * approved/partial/rejected → paid + write-off).
 *
 * Idempotent — re-running produces zero new rows. Mirrors the style of
 * PaymentsDemoSeeder (firstOrCreate / existence-checks throughout).
 */
class InsuranceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $patients = Patient::query()->orderBy('id')->limit(3)->get();

        if ($patients->count() < 3) {
            $this->command->warn('No patients to attach policies to.');

            return;
        }

        $insurers = Insurer::query()->orderBy('id')->get();
        if ($insurers->count() < 2) {
            $this->command->warn('Need at least 2 insurers seeded (run InsurerSeeder first).');

            return;
        }

        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            $this->command->warn('No User exists to act as the audit-log actor.');

            return;
        }

        $service = app(InsuranceService::class);

        // -----------------------------------------------------------------
        // 1) Policies — one primary per patient, plus a secondary on patient #1
        // -----------------------------------------------------------------
        $policiesCreated = 0;
        $primaryPolicyByPatient = [];

        foreach ($patients as $index => $patient) {
            $insurer = $insurers[$index % $insurers->count()];
            $goldPlan = InsurancePlan::query()
                ->where('insurer_id', $insurer->id)
                ->where('tier', 'gold')
                ->first();

            if (! $goldPlan) {
                continue;
            }

            $primary = PatientInsurancePolicy::firstOrCreate(
                [
                    'patient_id' => $patient->id,
                    'insurer_id' => $insurer->id,
                ],
                [
                    'plan_id' => $goldPlan->id,
                    'policy_number' => 'DEMO-'.strtoupper(Str::random(8)),
                    'holder_name' => $patient->name ?? 'Demo Holder',
                    'holder_relationship' => PatientInsurancePolicy::REL_SELF,
                    'status' => PatientInsurancePolicy::STATUS_ACTIVE,
                    'is_primary' => true,
                    'priority' => 1,
                    'effective_from' => now()->subDays(90)->toDateString(),
                    'effective_until' => now()->addDays(275)->toDateString(),
                ]
            );

            if ($primary->wasRecentlyCreated) {
                $policiesCreated++;
            }

            $primaryPolicyByPatient[$patient->id] = $primary;
        }

        // Secondary policy for patient #1 (different insurer + Silver plan)
        $firstPatient = $patients->first();
        $primaryInsurerId = $primaryPolicyByPatient[$firstPatient->id]->insurer_id ?? null;
        $secondaryInsurer = $insurers->firstWhere(fn ($i) => $i->id !== $primaryInsurerId);

        if ($secondaryInsurer) {
            $silverPlan = InsurancePlan::query()
                ->where('insurer_id', $secondaryInsurer->id)
                ->where('tier', 'silver')
                ->first();

            if ($silverPlan) {
                $secondary = PatientInsurancePolicy::firstOrCreate(
                    [
                        'patient_id' => $firstPatient->id,
                        'insurer_id' => $secondaryInsurer->id,
                    ],
                    [
                        'plan_id' => $silverPlan->id,
                        'policy_number' => 'DEMO-'.strtoupper(Str::random(8)),
                        'holder_name' => $firstPatient->name ?? 'Demo Holder',
                        'holder_relationship' => PatientInsurancePolicy::REL_SELF,
                        'status' => PatientInsurancePolicy::STATUS_ACTIVE,
                        'is_primary' => false,
                        'priority' => 2,
                        'effective_from' => now()->subDays(90)->toDateString(),
                        'effective_until' => now()->addDays(275)->toDateString(),
                    ]
                );

                if ($secondary->wasRecentlyCreated) {
                    $policiesCreated++;
                }
            }
        }

        // -----------------------------------------------------------------
        // 2) Draft claims from visits that already have charges
        // -----------------------------------------------------------------
        $patientIds = $patients->pluck('id')->all();

        $visits = Visit::query()
            ->has('visitCharges')
            ->whereNotNull('patient_id')
            ->whereIn('patient_id', $patientIds)
            ->orderBy('id')
            ->limit(5)
            ->get();

        // Fallback: any visit with charges if none of the seeded patients have any.
        if ($visits->isEmpty()) {
            $visits = Visit::query()
                ->has('visitCharges')
                ->whereNotNull('patient_id')
                ->orderBy('id')
                ->limit(5)
                ->get();
        }

        $claims = collect();
        $claimsCreated = 0;

        foreach ($visits as $visit) {
            $primary = $primaryPolicyByPatient[$visit->patient_id]
                ?? PatientInsurancePolicy::query()
                    ->where('patient_id', $visit->patient_id)
                    ->where('is_primary', true)
                    ->active()
                    ->first();

            if (! $primary) {
                continue;
            }

            $beforeCount = InsuranceClaim::query()
                ->where('visit_id', $visit->id)
                ->where('patient_policy_id', $primary->id)
                ->count();

            $claim = $service->createClaimFromVisit($visit, $primary, $user);

            if ($claim && InsuranceClaim::query()
                ->where('visit_id', $visit->id)
                ->where('patient_policy_id', $primary->id)
                ->count() > $beforeCount
            ) {
                $claimsCreated++;
            }

            if ($claim) {
                $claims->push($claim);
            }
        }

        // -----------------------------------------------------------------
        // 3) Walk a variety of claims through the state machine
        // -----------------------------------------------------------------
        $paymentsCreated = 0;
        $writeOffsCreated = 0;

        // Claim 1 — stay at draft (no-op).
        // Claim 2 — submitted only.
        $claim2 = $claims->get(1);
        if ($claim2 && $claim2->status === InsuranceClaim::STATUS_DRAFT) {
            $service->transition($claim2, InsuranceClaim::STATUS_SUBMITTED, $user, 'Demo submitted');
        }

        // Claim 3 — submitted → under_review → approved → paid (full).
        $claim3 = $claims->get(2);
        if ($claim3 && $claim3->status === InsuranceClaim::STATUS_DRAFT) {
            $claim3 = $service->transition($claim3, InsuranceClaim::STATUS_SUBMITTED, $user, 'Demo submitted');
            $claim3 = $service->transition($claim3, InsuranceClaim::STATUS_UNDER_REVIEW, $user, 'Demo review');
            $claim3 = $service->transition(
                $claim3,
                InsuranceClaim::STATUS_APPROVED,
                $user,
                'Auto-approved demo',
                [
                    'approved_amount' => $claim3->insurer_payable,
                    'decision_notes' => 'Auto-approved demo',
                ]
            );

            if ((float) $claim3->insurer_payable > 0) {
                $service->recordInsurerPayment(
                    $claim3,
                    (float) $claim3->insurer_payable,
                    'transfer',
                    'DEMO-WIRE-'.now()->timestamp,
                    null,
                    $user
                );
                $paymentsCreated++;
            }
        }

        // Claim 4 — submitted → under_review → partially_approved → partial paid + write-off.
        $claim4 = $claims->get(3);
        if ($claim4 && $claim4->status === InsuranceClaim::STATUS_DRAFT) {
            $claim4 = $service->transition($claim4, InsuranceClaim::STATUS_SUBMITTED, $user, 'Demo submitted');
            $claim4 = $service->transition($claim4, InsuranceClaim::STATUS_UNDER_REVIEW, $user, 'Demo review');

            $payable = (float) $claim4->insurer_payable;
            $approved = round($payable * 0.7, 3);
            $rejected = round($payable * 0.3, 3);

            $claim4 = $service->transition(
                $claim4,
                InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                $user,
                'Partial coverage demo',
                [
                    'approved_amount' => $approved,
                    'rejected_amount' => $rejected,
                    'decision_notes' => 'Partial coverage demo',
                ]
            );

            if ($approved > 0) {
                $service->recordInsurerPayment(
                    $claim4,
                    $approved,
                    'transfer',
                    'DEMO-WIRE-PARTIAL-'.now()->timestamp,
                    null,
                    $user
                );
                $paymentsCreated++;
            }

            // Write off whatever residual remains (after partial payment).
            $fresh = $claim4->fresh();
            if ($fresh && $fresh->balanceDue() > 0.001) {
                $service->writeOff(
                    $fresh,
                    round($fresh->balanceDue(), 3),
                    'Demo write-off of unrecovered balance',
                    $user
                );
                $writeOffsCreated++;
            }
        }

        // Claim 5 — submitted → rejected.
        $claim5 = $claims->get(4);
        if ($claim5 && $claim5->status === InsuranceClaim::STATUS_DRAFT) {
            $claim5 = $service->transition($claim5, InsuranceClaim::STATUS_SUBMITTED, $user, 'Demo submitted');
            $service->transition(
                $claim5,
                InsuranceClaim::STATUS_REJECTED,
                $user,
                'Service not covered demo',
                ['decision_notes' => 'Service not covered demo']
            );
        }

        $this->command->info(sprintf(
            'Insurance demo: %d policies, %d claims, %d payments, %d write-offs.',
            $policiesCreated,
            $claimsCreated,
            $paymentsCreated,
            $writeOffsCreated
        ));
    }
}
