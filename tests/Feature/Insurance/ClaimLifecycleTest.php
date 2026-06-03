<?php

namespace Tests\Feature\Insurance;

use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\VisitCharge;
use App\Services\Insurance\InsuranceService;
use Database\Seeders\InsuranceCoverageRuleSeeder;
use Database\Seeders\InsurancePlanSeeder;
use Database\Seeders\InsurerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Full end-to-end cover for the InsuranceService claim lifecycle.
 *
 * Walks a claim from draft → submitted → under_review → approved → paid
 * with a real insurer payment, then asserts the General Ledger nets to
 * zero. If the accounting hooks ever drop a side of a posting, this is
 * the safety net that catches it.
 */
class ClaimLifecycleTest extends TestCase
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

    public function test_full_claim_lifecycle_keeps_books_balanced(): void
    {
        $f = $this->seedClinicFixtures();

        // 1) Visit with a real charge — the calculator buckets fees_total
        //    into the consultation kind which Gold-tier covers at 100%.
        $visit = $this->makeVisit([
            'fees_total' => 100,
            'items_price_total' => 0,
            'packages_price_total' => 0,
            'discount_total' => 0,
        ]);

        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => $f['branch']->id,
            'label' => 'Test consultation',
            'qty' => 1,
            'unit_price_snapshot' => 100,
            'line_total' => 100,
        ]);

        // 2) Primary policy on Warba Gold (100% consultation coverage).
        $insurer = Insurer::query()->where('code', 'WARBA')->firstOrFail();
        $plan = $insurer->plans()->where('code', 'WARBA-GOLD')->firstOrFail();

        $policy = PatientInsurancePolicy::create([
            'patient_id' => $f['patient']->id,
            'insurer_id' => $insurer->id,
            'plan_id' => $plan->id,
            'policy_number' => 'TEST-001',
            'status' => PatientInsurancePolicy::STATUS_ACTIVE,
            'is_primary' => true,
            'priority' => 1,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_until' => now()->addYear()->toDateString(),
        ]);

        $user = $f['user'];
        $service = app(InsuranceService::class);

        // 3) Draft → submitted → under_review → approved.
        $claim = $service->createClaimFromVisit($visit, $policy, $user);
        $this->assertSame(InsuranceClaim::STATUS_DRAFT, $claim->status);
        $this->assertGreaterThan(0, (float) $claim->insurer_payable);

        $claim = $service->transition($claim, InsuranceClaim::STATUS_SUBMITTED, $user, 'Test submit');
        $claim = $service->transition($claim, InsuranceClaim::STATUS_UNDER_REVIEW, $user);
        $claim = $service->transition(
            $claim,
            InsuranceClaim::STATUS_APPROVED,
            $user,
            null,
            ['approved_amount' => (float) $claim->insurer_payable]
        );

        // 4) Record the insurer payment for the full payable. Service
        //    should auto-transition the claim to 'paid'.
        $payment = $service->recordInsurerPayment(
            $claim,
            (float) $claim->insurer_payable,
            'transfer',
            'TEST-WIRE-1',
            null,
            $user
        );
        $this->assertNotNull($payment);

        $claim->refresh();
        $this->assertSame(InsuranceClaim::STATUS_PAID, $claim->status);
        $this->assertEqualsWithDelta(
            (float) $claim->insurer_payable,
            (float) $claim->paid_amount,
            0.001
        );

        // 5) Trial balance — posted debits must equal posted credits.
        $dr = (float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->sum('debit');

        $cr = (float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->sum('credit');

        $this->assertEqualsWithDelta(
            0.0,
            $dr - $cr,
            0.001,
            'Books must balance after full claim cycle.'
        );
    }
}
