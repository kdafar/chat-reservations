<?php

namespace Database\Seeders;

use App\Models\Inpatient\Admission;
use App\Models\Inpatient\Bed;
use App\Models\Insurance\InsurancePreauthorization;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCharge;
use App\Models\VisitItem;
use App\Services\Clinic\VisitCostingService;
use App\Services\Inpatient\AdmissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Surface-level demo polish so the customer-pitch click-through doesn't
 * land on empty tables for the recently-built features:
 *   - Pre-authorizations (Wave 2 visit-scoped UI)
 *   - Per-line discounts on visit items + charges
 *   - Extra inpatient admissions so the inpatient module looks lived-in
 *
 * Idempotent — each block has a count-based bailout so re-running is safe.
 * Run via `php artisan db:seed --class=PitchDemoSeeder` or as part of the
 * main DatabaseSeeder run.
 */
class PitchDemoSeeder extends Seeder
{
    private const PREAUTH_TARGET = 10;

    private const ADMISSION_TARGET = 6;

    private const DISCOUNT_ITEM_RATIO = 0.25;

    public function run(): void
    {
        $this->seedPreauthorizations();
        $this->seedPerLineDiscounts();
        $this->seedAdmissions();
        $this->seedLabOrders();
    }

    /**
     * Add a handful of lab orders + results on real visits so the new
     * Lab Orders tab on the visit edit page has something to show.
     */
    protected function seedLabOrders(): void
    {
        $existing = \App\Models\Lab\LabOrder::query()->count();
        if ($existing >= 4) {
            $this->command?->line("  lab orders: {$existing} ≥ target — skip");
            return;
        }

        $tests = \App\Models\Lab\LabTest::query()
            ->whereIn('code', ['CBC', 'GLU', 'HBA1C', 'TSH', 'LIPID'])
            ->get()
            ->keyBy('code');
        if ($tests->isEmpty()) {
            $this->command?->warn('  lab orders: no lab tests catalogued — run LabTestSeeder first');
            return;
        }

        $visits = \App\Models\Visit::query()
            ->whereNotNull('patient_id')
            ->orderByDesc('id')
            ->limit(4)
            ->get();
        if ($visits->isEmpty()) {
            return;
        }

        $bundles = [
            [['code' => 'CBC', 'result' => 'normal', 'flag' => 'normal'],
             ['code' => 'GLU', 'result' => '145', 'flag' => 'high']],
            [['code' => 'HBA1C', 'result' => '7.2', 'flag' => 'high']],
            [['code' => 'LIPID', 'result' => 'see breakdown', 'flag' => 'normal'],
             ['code' => 'TSH', 'result' => '2.1', 'flag' => 'normal']],
            [['code' => 'CBC', 'result' => 'normal', 'flag' => 'normal']],
        ];

        $created = 0;
        foreach ($visits as $i => $visit) {
            $bundle = $bundles[$i % count($bundles)];
            $order = \App\Models\Lab\LabOrder::create([
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'branch_id' => $visit->branch_id,
                'doctor_id' => $visit->doctor_id,
                'ordered_by_user_id' => 1,
                'status' => \App\Models\Lab\LabOrder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            foreach ($bundle as $line) {
                $test = $tests->get($line['code']);
                if (! $test) continue;
                \App\Models\Lab\LabOrderItem::create([
                    'lab_order_id' => $order->id,
                    'lab_test_id' => $test->id,
                    'status' => \App\Models\Lab\LabOrderItem::STATUS_COMPLETED,
                    'result_value' => $line['result'],
                    'result_unit' => $test->unit,
                    'reference_range_snapshot' => $test->reference_range,
                    'flag' => $line['flag'],
                    'price_snapshot' => $test->default_price,
                    'completed_at' => now(),
                    'completed_by_user_id' => 1,
                ]);
            }
            $created++;
        }

        $this->command?->info("  lab orders: +{$created} (total ".\App\Models\Lab\LabOrder::query()->count().')');
    }

    /**
     * Seed pre-authorizations across a spread of statuses so the new
     * VisitPreauthorizationsRelationManager has something to render.
     */
    protected function seedPreauthorizations(): void
    {
        $existing = InsurancePreauthorization::query()->count();
        if ($existing >= self::PREAUTH_TARGET) {
            $this->command?->line("  pre-auths: {$existing} ≥ target — skip");
            return;
        }

        $policies = PatientInsurancePolicy::query()
            ->active()
            ->with('patient')
            ->orderBy('id')
            ->get();

        if ($policies->isEmpty()) {
            $this->command?->warn('  pre-auths: no active policies to attach to — skip');
            return;
        }

        $blueprints = [
            ['status' => InsurancePreauthorization::STATUS_DRAFT,              'services' => [['label' => 'MRI brain', 'estimated_amount' => 120]], 'approved' => null],
            ['status' => InsurancePreauthorization::STATUS_SUBMITTED,          'services' => [['label' => 'CT scan abdomen', 'estimated_amount' => 90], ['label' => 'Contrast media', 'estimated_amount' => 15]], 'approved' => null],
            ['status' => InsurancePreauthorization::STATUS_UNDER_REVIEW,       'services' => [['label' => 'Cardiac stress test', 'estimated_amount' => 80]], 'approved' => null],
            ['status' => InsurancePreauthorization::STATUS_APPROVED,           'services' => [['label' => 'Knee arthroscopy', 'estimated_amount' => 450]], 'approved' => 450],
            ['status' => InsurancePreauthorization::STATUS_PARTIALLY_APPROVED, 'services' => [['label' => 'Physiotherapy package (10 sessions)', 'estimated_amount' => 200]], 'approved' => 140],
            ['status' => InsurancePreauthorization::STATUS_REJECTED,           'services' => [['label' => 'Cosmetic procedure', 'estimated_amount' => 300]], 'approved' => 0],
            ['status' => InsurancePreauthorization::STATUS_EXPIRED,            'services' => [['label' => 'Dental extraction', 'estimated_amount' => 60]], 'approved' => null],
        ];

        $actor = User::query()->orderBy('id')->value('id');
        $created = 0;
        $need = self::PREAUTH_TARGET - $existing;

        for ($i = 0; $i < $need; $i++) {
            $policy = $policies[$i % $policies->count()];
            $blueprint = $blueprints[$i % count($blueprints)];

            $estTotal = collect($blueprint['services'])->sum('estimated_amount');

            // Try to anchor to a real visit when possible (so the pre-auth
            // appears on the visit panel demo too); otherwise visit_id null.
            $visit = Visit::query()
                ->where('patient_id', $policy->patient_id)
                ->orderByDesc('id')
                ->value('id');

            $row = InsurancePreauthorization::create([
                'patient_policy_id' => $policy->id,
                'visit_id' => $visit,
                'branch_id' => $policy->patient?->branch_id,
                'requested_by_user_id' => $actor,
                'services' => $blueprint['services'],
                'estimated_total' => $estTotal,
                'requested_at' => now()->subDays(rand(1, 14)),
                'reference_no' => 'PA-DEMO-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                'status' => $blueprint['status'],
                'approved_amount' => $blueprint['approved'],
                'valid_from' => $blueprint['status'] === InsurancePreauthorization::STATUS_APPROVED ? now()->toDateString() : null,
                'valid_until' => $blueprint['status'] === InsurancePreauthorization::STATUS_APPROVED ? now()->addDays(30)->toDateString() : null,
                'decision_notes' => match ($blueprint['status']) {
                    InsurancePreauthorization::STATUS_APPROVED => 'Approved as requested.',
                    InsurancePreauthorization::STATUS_PARTIALLY_APPROVED => 'Approved 70% — coverage cap.',
                    InsurancePreauthorization::STATUS_REJECTED => 'Service not covered under plan.',
                    InsurancePreauthorization::STATUS_EXPIRED => 'Validity period elapsed.',
                    default => null,
                },
                'decided_at' => in_array($blueprint['status'], [
                    InsurancePreauthorization::STATUS_APPROVED,
                    InsurancePreauthorization::STATUS_PARTIALLY_APPROVED,
                    InsurancePreauthorization::STATUS_REJECTED,
                ], true) ? now()->subDays(rand(0, 3)) : null,
                'decided_by_user_id' => in_array($blueprint['status'], [
                    InsurancePreauthorization::STATUS_APPROVED,
                    InsurancePreauthorization::STATUS_PARTIALLY_APPROVED,
                    InsurancePreauthorization::STATUS_REJECTED,
                ], true) ? $actor : null,
            ]);

            $created++;
        }

        $this->command?->info("  pre-auths: +{$created} (total ".InsurancePreauthorization::query()->count().')');
    }

    /**
     * Sprinkle per-line discounts onto ~25% of existing VisitItem and
     * VisitCharge rows so the new discount column has populated values
     * when the customer clicks into a visit.
     */
    protected function seedPerLineDiscounts(): void
    {
        $alreadyDiscounted = VisitItem::query()->where('discount_amount', '>', 0)->count()
            + VisitCharge::query()->where('discount_amount', '>', 0)->count();

        if ($alreadyDiscounted > 0) {
            $this->command?->line("  discounts: {$alreadyDiscounted} rows already have a discount — skip");
            return;
        }

        $touchedVisits = [];
        $itemHits = 0;
        $chargeHits = 0;

        VisitItem::query()
            ->orderBy('id')
            ->chunk(200, function ($items) use (&$touchedVisits, &$itemHits) {
                foreach ($items as $item) {
                    if (mt_rand(1, 100) > self::DISCOUNT_ITEM_RATIO * 100) {
                        continue;
                    }
                    $linePrice = (float) $item->line_price_total;
                    if ($linePrice <= 0) {
                        continue;
                    }
                    // 5-10% discount, rounded to 3 decimals.
                    $discount = round($linePrice * (mt_rand(5, 10) / 100), 3);
                    $item->forceFill(['discount_amount' => $discount])->save();
                    $touchedVisits[$item->visit_id] = true;
                    $itemHits++;
                }
            });

        VisitCharge::query()
            ->orderBy('id')
            ->chunk(200, function ($charges) use (&$touchedVisits, &$chargeHits) {
                foreach ($charges as $charge) {
                    if (mt_rand(1, 100) > self::DISCOUNT_ITEM_RATIO * 100) {
                        continue;
                    }
                    $lineTotal = (float) $charge->line_total;
                    if ($lineTotal <= 0) {
                        continue;
                    }
                    $discount = round($lineTotal * (mt_rand(5, 10) / 100), 3);
                    $charge->forceFill(['discount_amount' => $discount])->save();
                    $touchedVisits[$charge->visit_id] = true;
                    $chargeHits++;
                }
            });

        // Recompute affected visits so fees_total / items_price_total roll up.
        $svc = app(VisitCostingService::class);
        $recomputed = 0;
        foreach (array_keys($touchedVisits) as $vid) {
            $visit = Visit::query()->find($vid);
            if (! $visit) {
                continue;
            }
            try {
                $svc->compute($visit);
                $recomputed++;
            } catch (\Throwable $e) {
                $this->command?->warn("  visit#{$vid} recompute failed: {$e->getMessage()}");
            }
        }

        $this->command?->info("  discounts: {$itemHits} items + {$chargeHits} charges, recomputed {$recomputed} visits");
    }

    /**
     * Add a handful of admissions so the inpatient screens have lived-in data:
     *   - some active (so the bed map shows occupancy)
     *   - some recently discharged (so the discharge audit log has history)
     */
    protected function seedAdmissions(): void
    {
        $existing = Admission::query()->count();
        if ($existing >= self::ADMISSION_TARGET) {
            $this->command?->line("  admissions: {$existing} ≥ target — skip");
            return;
        }

        $beds = Bed::query()
            ->where('status', Bed::STATUS_AVAILABLE)
            ->with('ward')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($beds->isEmpty()) {
            $this->command?->warn('  admissions: no available beds — skip');
            return;
        }

        $patientsInUse = Admission::query()
            ->whereIn('status', [Admission::STATUS_ACTIVE])
            ->pluck('patient_id')
            ->all();

        $patients = DB::table('patients')
            ->whereNotIn('id', $patientsInUse)
            ->orderBy('id')
            ->limit(self::ADMISSION_TARGET - $existing + 2)
            ->get();

        if ($patients->isEmpty()) {
            $this->command?->warn('  admissions: no free patients — skip');
            return;
        }

        $actor = User::query()->orderBy('id')->first();
        $admissionSvc = app(AdmissionService::class);

        $reasons = [
            'Pneumonia — IV antibiotics + monitoring',
            'Post-op observation (24h)',
            'Acute appendicitis — surgical admission',
            'Severe dehydration — IV fluids',
            'Chest pain — cardiac workup',
            'Asthma exacerbation — nebulization',
        ];

        $created = 0;
        $need = self::ADMISSION_TARGET - $existing;
        $bedIdx = 0;

        foreach ($patients as $patient) {
            if ($created >= $need) {
                break;
            }
            if ($bedIdx >= $beds->count()) {
                break;
            }

            $bed = $beds[$bedIdx++];
            $doctorId = (int) DB::table('doctors')->where('branch_id', $bed->ward->branch_id)->value('id');
            if (! $doctorId) {
                $doctorId = (int) DB::table('doctors')->value('id');
            }
            if (! $doctorId) {
                $this->command?->warn('  admissions: no doctors — skip');
                return;
            }

            try {
                $admission = $admissionSvc->admit([
                    'patient_id' => $patient->id,
                    'admitting_doctor_id' => $doctorId,
                    'branch_id' => $bed->ward->branch_id,
                    'partner_id' => $bed->ward->partner_id,
                    'admission_reason' => $reasons[$created % count($reasons)],
                    'diagnosis' => 'Demo diagnosis',
                    'admitted_at' => now()->subDays(rand(1, 7)),
                ], $bed, $actor);

                // Discharge half of them so the audit log has discharge events.
                if ($created % 2 === 1 && $actor) {
                    try {
                        $admissionSvc->discharge(
                            $admission,
                            $actor,
                            'Stable for discharge — follow-up in clinic.',
                            Admission::STATUS_DISCHARGED
                        );
                    } catch (\Throwable $e) {
                        $this->command?->warn("  discharge of admission#{$admission->id} failed: {$e->getMessage()}");
                    }
                }

                $created++;
            } catch (\Throwable $e) {
                $this->command?->warn("  admit patient#{$patient->id} failed: {$e->getMessage()}");
            }
        }

        $this->command?->info("  admissions: +{$created} (total ".Admission::query()->count().')');
    }
}
