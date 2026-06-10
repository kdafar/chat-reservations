<?php

namespace Tests\Feature\V2;

use App\Models\Insurance\InsurancePlan;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Phase 6 — reception captures a patient's insurance from the visit modal:
 * civil id + insurer/plan/policy → creates a policy (first = primary) and
 * stores the civil id on the patient.
 */
class VisitInsuranceCaptureTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
    }

    private function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');
        $u = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@t.local',
            'password' => Hash::make('password'), 'status' => 'active',
        ]);
        $u->assignRole('admin');

        return $u;
    }

    private function insurerWithPlan(): array
    {
        $insurer = Insurer::create(['name' => 'Gulf Insurance', 'code' => 'GIG-'.uniqid(), 'is_active' => true]);
        $plan = InsurancePlan::create(['insurer_id' => $insurer->id, 'name' => 'Gold', 'code' => 'G-'.uniqid(), 'is_active' => true]);

        return [$insurer, $plan];
    }

    public function test_reception_can_attach_insurance_and_civil_id(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit(['status' => 'awaiting_payment', 'checked_in_at' => now(), 'completed_at' => null]);
        [$insurer, $plan] = $this->insurerWithPlan();

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/insurance/attach", [
                'civil_id' => '290010112345',
                'insurer_id' => $insurer->id,
                'plan_id' => $plan->id,
                'policy_number' => 'POL-998877',
            ])
            ->assertOk()->assertJson(['ok' => true]);

        $policy = PatientInsurancePolicy::where('patient_id', $f['patient']->id)->first();
        $this->assertNotNull($policy);
        $this->assertSame('POL-998877', $policy->policy_number);
        $this->assertTrue((bool) $policy->is_primary, 'first policy should be primary');
        $this->assertSame('290010112345', $f['patient']->fresh()->civil_id);
    }

    public function test_attach_rejects_inactive_insurer_or_plan(): void
    {
        $visit = $this->makeVisit(['status' => 'awaiting_payment', 'checked_in_at' => now(), 'completed_at' => null]);
        $insurer = Insurer::create(['name' => 'Retired Co', 'code' => 'OLD-'.uniqid(), 'is_active' => false]);
        $plan = InsurancePlan::create(['insurer_id' => $insurer->id, 'name' => 'Legacy', 'code' => 'L-'.uniqid(), 'is_active' => false]);

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/insurance/attach", [
                'insurer_id' => $insurer->id, 'plan_id' => $plan->id, 'policy_number' => 'P-1',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('patient_insurance_policies', 0);
    }

    public function test_plan_must_belong_to_the_chosen_insurer(): void
    {
        $visit = $this->makeVisit(['status' => 'awaiting_payment', 'checked_in_at' => now(), 'completed_at' => null]);
        [$insurerA] = $this->insurerWithPlan();
        [, $planB] = $this->insurerWithPlan(); // a plan from a DIFFERENT insurer

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/insurance/attach", [
                'insurer_id' => $insurerA->id,
                'plan_id' => $planB->id,
                'policy_number' => 'POL-1',
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseCount('patient_insurance_policies', 0);
    }
}
