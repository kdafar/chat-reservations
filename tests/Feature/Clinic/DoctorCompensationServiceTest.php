<?php

namespace Tests\Feature\Clinic;

use App\Models\DoctorCompensationLedger;
use App\Models\DoctorCompensationProfile;
use App\Services\Clinic\DoctorCompensationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class DoctorCompensationServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected DoctorCompensationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(DoctorCompensationService::class);

        config([
            'clinic.doctor_comp_enabled' => true,
            'clinic.doctor_comp_only_on_completed' => true,
        ]);
    }

    public function test_percentage_fees_only_computes_correct_cut(): void
    {
        $f = $this->seedClinicFixtures();
        DoctorCompensationProfile::create([
            'doctor_id' => $f['doctor']->id,
            'type' => 'percentage',
            'basis' => 'fees_only',
            'percentage_rate' => 40.0,
            'is_active' => 1,
        ]);

        $visit = $this->makeVisit([
            'status' => 'completed',
            'fees_total' => 100.000,
            'discount_total' => 0,
            'items_cost_total' => 0,
            'items_price_total' => 0,
            'profit_total' => 100.000,
        ]);

        $ledger = $this->svc->sync($visit);

        $this->assertInstanceOf(DoctorCompensationLedger::class, $ledger);
        // 40% of 100 = 40
        $this->assertEqualsWithDelta(40.000, (float) $ledger->doctor_cut_amount, 0.001);
        $this->assertSame('percentage', $ledger->type_snapshot);
        $this->assertSame('fees_only', $ledger->basis_snapshot);
        $this->assertEqualsWithDelta(40.0, (float) $ledger->rate_snapshot, 0.001);
    }

    public function test_percentage_net_profit_uses_profit_total(): void
    {
        $f = $this->seedClinicFixtures();
        DoctorCompensationProfile::create([
            'doctor_id' => $f['doctor']->id,
            'type' => 'percentage',
            'basis' => 'net_profit',
            'percentage_rate' => 25.0,
            'is_active' => 1,
        ]);

        $visit = $this->makeVisit([
            'status' => 'completed',
            'fees_total' => 100.000,
            'discount_total' => 0,
            'items_cost_total' => 10.000,
            'items_price_total' => 30.000,
            'profit_total' => 120.000,
        ]);

        $ledger = $this->svc->sync($visit);

        // 25% of 120 = 30
        $this->assertEqualsWithDelta(30.000, (float) $ledger->doctor_cut_amount, 0.001);
    }

    public function test_salary_basis_produces_zero_cut(): void
    {
        $f = $this->seedClinicFixtures();
        DoctorCompensationProfile::create([
            'doctor_id' => $f['doctor']->id,
            'type' => 'salary',
            'basis' => 'fees_only',
            'is_active' => 1,
        ]);

        $visit = $this->makeVisit([
            'status' => 'completed',
            'fees_total' => 100.000,
            'profit_total' => 90.000,
        ]);

        $ledger = $this->svc->sync($visit);

        $this->assertNotNull($ledger);
        $this->assertEqualsWithDelta(0.0, (float) $ledger->doctor_cut_amount, 0.001,
            'Salary basis: cut should be zero on per-visit ledger');
        $this->assertSame('salary', $ledger->type_snapshot);
    }

    public function test_discount_is_subtracted_from_fees_basis(): void
    {
        $f = $this->seedClinicFixtures();
        DoctorCompensationProfile::create([
            'doctor_id' => $f['doctor']->id,
            'type' => 'percentage',
            'basis' => 'fees_only',
            'percentage_rate' => 30.0,
            'is_active' => 1,
        ]);

        $visit = $this->makeVisit([
            'status' => 'completed',
            'fees_total' => 100.000,
            'discount_total' => 20.000,
            'profit_total' => 80.000,
        ]);

        $ledger = $this->svc->sync($visit);

        // (100 − 20) × 30% = 24
        $this->assertEqualsWithDelta(24.000, (float) $ledger->doctor_cut_amount, 0.001);
    }

    public function test_sync_is_idempotent(): void
    {
        $f = $this->seedClinicFixtures();
        DoctorCompensationProfile::create([
            'doctor_id' => $f['doctor']->id,
            'type' => 'percentage',
            'basis' => 'fees_only',
            'percentage_rate' => 40.0,
            'is_active' => 1,
        ]);

        $visit = $this->makeVisit([
            'status' => 'completed',
            'fees_total' => 50.000,
            'profit_total' => 50.000,
        ]);

        $this->svc->sync($visit);
        $this->svc->sync($visit);
        $this->svc->sync($visit);

        $this->assertSame(1, DoctorCompensationLedger::where('visit_id', $visit->id)->count(),
            'sync must upsert: never produce more than one ledger per visit');
    }

    public function test_skips_when_visit_not_completed(): void
    {
        $f = $this->seedClinicFixtures();
        DoctorCompensationProfile::create([
            'doctor_id' => $f['doctor']->id,
            'type' => 'percentage',
            'basis' => 'fees_only',
            'percentage_rate' => 40.0,
            'is_active' => 1,
        ]);

        $visit = $this->makeVisit([
            'status' => 'in_progress',
            'fees_total' => 100.000,
        ]);

        $ledger = $this->svc->sync($visit);

        $this->assertNull($ledger, 'Should skip when doctor_comp_only_on_completed=true and visit not completed');
    }

    public function test_skips_when_feature_flag_off(): void
    {
        config(['clinic.doctor_comp_enabled' => false]);

        $f = $this->seedClinicFixtures();
        DoctorCompensationProfile::create([
            'doctor_id' => $f['doctor']->id,
            'type' => 'percentage', 'basis' => 'fees_only',
            'percentage_rate' => 40.0, 'is_active' => 1,
        ]);
        $visit = $this->makeVisit(['status' => 'completed', 'fees_total' => 100]);

        $this->assertNull($this->svc->sync($visit));
    }

    public function test_no_profile_defaults_to_salary_type_with_zero_cut(): void
    {
        // No DoctorCompensationProfile created
        $visit = $this->makeVisit([
            'status' => 'completed',
            'fees_total' => 100.000,
            'profit_total' => 100.000,
        ]);

        $ledger = $this->svc->sync($visit);

        $this->assertNotNull($ledger);
        $this->assertSame('salary', $ledger->type_snapshot);
        $this->assertEqualsWithDelta(0.0, (float) $ledger->doctor_cut_amount, 0.001);
    }
}
