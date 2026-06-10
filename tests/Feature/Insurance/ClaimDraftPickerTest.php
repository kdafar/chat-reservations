<?php

namespace Tests\Feature\Insurance;

use App\Http\Controllers\V2\ClaimsController;
use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\VisitCharge;
use App\Models\VisitPayment;
use App\Services\Insurance\InsuranceService;
use Database\Seeders\InsuranceCoverageRuleSeeder;
use Database\Seeders\InsurancePlanSeeder;
use Database\Seeders\InsurerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Covers the visit-picker + coverage-preview JSON endpoints that back the
 * redesigned "draft a claim from a visit" modal on the v2 Claims page.
 *
 * Routes are wired by the orchestrator, so we exercise the controller methods
 * directly with an authenticated user holding view_any_insurance_claims.
 */
class ClaimDraftPickerTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seed(InsurerSeeder::class);
        $this->seed(InsurancePlanSeeder::class);
        $this->seed(InsuranceCoverageRuleSeeder::class);
    }

    /**
     * Grant the seeded fixture user the claim-view permission and log them in.
     * Also assigns the admin role so the BelongsToBranchScope global scope
     * returns all rows (the fixture user has no branch_user pivot).
     */
    protected function actAsClaimViewer(): array
    {
        $f = $this->seedClinicFixtures();
        $user = $f['user'];

        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        Permission::findOrCreate('view_any_insurance_claims', 'web');
        Permission::findOrCreate('create_insurance_claims', 'web');
        $user->assignRole('admin');
        $user->givePermissionTo(['view_any_insurance_claims', 'create_insurance_claims']);

        Auth::login($user);

        return $f;
    }

    protected function controller(): ClaimsController
    {
        return app(ClaimsController::class);
    }

    /** Attach the authenticated user resolver to a request (mirrors the auth middleware). */
    protected function req(array $query = []): Request
    {
        $request = Request::create('/', 'GET', $query);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }

    protected function policyOnGold(int $patientId): PatientInsurancePolicy
    {
        $insurer = Insurer::query()->where('code', 'WARBA')->firstOrFail();
        $plan = $insurer->plans()->where('code', 'WARBA-GOLD')->firstOrFail();

        return PatientInsurancePolicy::create([
            'patient_id' => $patientId,
            'insurer_id' => $insurer->id,
            'plan_id' => $plan->id,
            'policy_number' => 'TEST-PICK-'.$patientId,
            'status' => PatientInsurancePolicy::STATUS_ACTIVE,
            'is_primary' => true,
            'priority' => 1,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_until' => now()->addYear()->toDateString(),
        ]);
    }

    public function test_claimable_visits_lists_only_visits_with_active_policy_and_no_open_claim(): void
    {
        $f = $this->actAsClaimViewer();

        // Visit A: patient has an active policy, no claim → should appear.
        $visitA = $this->makeVisit(['fees_total' => 100, 'booking_code' => 'BK-A']);
        VisitCharge::create([
            'visit_id' => $visitA->id, 'branch_id' => $f['branch']->id,
            'label' => 'Consult', 'qty' => 1, 'unit_price_snapshot' => 100, 'line_total' => 100,
        ]);
        $this->policyOnGold($f['patient']->id);

        // Visit B: same patient but already has a non-void claim → excluded.
        $visitB = $this->makeVisit(['fees_total' => 50, 'booking_code' => 'BK-B']);
        InsuranceClaim::create([
            'visit_id' => $visitB->id,
            'patient_policy_id' => PatientInsurancePolicy::query()->where('patient_id', $f['patient']->id)->first()->id,
            'branch_id' => $f['branch']->id,
            'claim_number' => 'CLM-EXISTING-1',
            'status' => InsuranceClaim::STATUS_DRAFT,
            'total_charged' => 50, 'patient_copay' => 0, 'insurer_payable' => 50,
            'approved_amount' => 0, 'rejected_amount' => 0, 'paid_amount' => 0, 'write_off_amount' => 0,
        ]);

        $json = $this->controller()->claimableVisits($this->req())->getData(true);
        $ids = array_column($json['data'], 'id');

        $this->assertContains($visitA->id, $ids, 'Visit with active policy + no claim should be listed.');
        $this->assertNotContains($visitB->id, $ids, 'Visit with an existing non-void claim should be excluded.');

        // The booking_code search filter narrows the list.
        $filtered = $this->controller()->claimableVisits($this->req(['q' => 'BK-A']))->getData(true);
        $this->assertSame([$visitA->id], array_column($filtered['data'], 'id'));

        // Each row carries a primary-policy header.
        $row = collect($json['data'])->firstWhere('id', $visitA->id);
        $this->assertNotNull($row['primary_policy']);
        $this->assertArrayHasKey('insurer', $row['primary_policy']);
    }

    public function test_preview_visit_returns_coverage_rows_totals_and_already_paid(): void
    {
        $f = $this->actAsClaimViewer();

        $visit = $this->makeVisit(['fees_total' => 100, 'booking_code' => 'BK-PREVIEW']);
        VisitCharge::create([
            'visit_id' => $visit->id, 'branch_id' => $f['branch']->id,
            'label' => 'Consult', 'qty' => 1, 'unit_price_snapshot' => 100, 'line_total' => 100,
        ]);
        $this->policyOnGold($f['patient']->id);

        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 10, 'method' => 'cash', 'status' => 'paid',
        ]);

        $json = $this->controller()->previewVisit($this->req(), $visit)->getData(true);

        $this->assertTrue($json['has_policy']);
        $this->assertFalse($json['claim_exists']);
        $this->assertNotEmpty($json['rows']);
        $this->assertEqualsWithDelta(10.0, $json['already_paid'], 0.001);

        // Coverage % is insurer_covers / gross for each row.
        foreach ($json['rows'] as $r) {
            $expected = $r['gross'] > 0 ? round(($r['insurer_covers'] / $r['gross']) * 100, 1) : 0.0;
            $this->assertEqualsWithDelta($expected, $r['coverage_pct'], 0.1);
        }

        // Totals are present and gross ties to the estimate.
        $this->assertArrayHasKey('gross', $json['totals']);
        $this->assertArrayHasKey('insurer_total', $json['totals']);
        $this->assertArrayHasKey('patient_total', $json['totals']);

        // Sanity against the service estimate.
        $estimate = app(InsuranceService::class)->estimateForVisit($visit);
        $this->assertEqualsWithDelta((float) $estimate['totals']['gross'], $json['totals']['gross'], 0.001);
    }

    public function test_non_claimable_visits_are_blocked_in_picker_preview_and_draft(): void
    {
        $f = $this->actAsClaimViewer();
        $this->policyOnGold($f['patient']->id);

        // Claimable: served (completed) + has charges.
        $ok = $this->makeVisit(['status' => 'completed', 'fees_total' => 100, 'booking_code' => 'BK-OK']);
        // Not served yet (status created) — must be excluded even with charges.
        $created = $this->makeVisit(['status' => 'created', 'fees_total' => 100, 'completed_at' => null, 'booking_code' => 'BK-NEW']);
        // Served but zero charges — nothing to claim.
        $zero = $this->makeVisit(['status' => 'completed', 'fees_total' => 0, 'packages_price_total' => 0, 'items_price_total' => 0, 'booking_code' => 'BK-ZERO']);

        $ids = array_column($this->controller()->claimableVisits($this->req())->getData(true)['data'], 'id');
        $this->assertContains($ok->id, $ids);
        $this->assertNotContains($created->id, $ids, 'A not-yet-served (created) visit must be excluded.');
        $this->assertNotContains($zero->id, $ids, 'A zero-charge visit must be excluded.');

        // Preview rejects a non-claimable visit.
        $this->assertSame(422, $this->controller()->previewVisit($this->req(), $created)->getStatusCode());

        // Draft refuses a zero-charge visit — no claim row created.
        $post = Request::create('/', 'POST', ['visit_id' => $zero->id]);
        $post->setUserResolver(fn () => Auth::user());
        $this->controller()->createFromVisit($post);
        $this->assertDatabaseMissing('insurance_claims', ['visit_id' => $zero->id]);
    }

    public function test_endpoints_require_view_permission(): void
    {
        $f = $this->seedClinicFixtures();
        Auth::login($f['user']); // no permission granted

        $visit = $this->makeVisit(['fees_total' => 100]);

        try {
            $this->controller()->claimableVisits($this->req());
            $this->fail('Expected 403 for claimableVisits without permission.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        try {
            $this->controller()->previewVisit($this->req(), $visit);
            $this->fail('Expected 403 for previewVisit without permission.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
