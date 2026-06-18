<?php

namespace Tests\Feature\Insurance;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\VisitCharge;
use App\Services\Accounting\AgingService;
use App\Services\Accounting\ChartOfAccounts;
use App\Services\Insurance\InsuranceService;
use Database\Seeders\InsuranceCoverageRuleSeeder;
use Database\Seeders\InsurancePlanSeeder;
use Database\Seeders\InsurerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Regression cover for the two gaps found in review: (1) a claim reaching
 * 'paid' must NOT reverse the insurer-AR reclass, and (2) with a dedicated
 * insurer AR account the balances must be correct per ACCOUNT (not merely
 * Dr = Cr) and the AR aging must attribute insurer vs patient balances.
 */
class InsurerArReclassTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    private Account $insurerAr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seed(InsurerSeeder::class);
        $this->seed(InsurancePlanSeeder::class);
        $this->seed(InsuranceCoverageRuleSeeder::class);

        // Give Warba a dedicated AR control account so the reclass isn't a wash.
        $this->insurerAr = Account::create([
            'code' => '1145', 'name' => 'AR — Warba Insurance', 'type' => Account::TYPE_ASSET,
            'parent_id' => $this->account('1100')->id, 'is_active' => true,
        ]);
        Insurer::query()->where('code', 'WARBA')->update(['ar_account_id' => $this->insurerAr->id]);
        app(ChartOfAccounts::class)->refresh();
    }

    private function approvedClaim(float $billed, float $approved): array
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit(['fees_total' => $billed, 'items_price_total' => 0, 'packages_price_total' => 0, 'discount_total' => 0]);
        VisitCharge::create(['visit_id' => $visit->id, 'branch_id' => $f['branch']->id, 'label' => 'Consult', 'qty' => 1, 'unit_price_snapshot' => $billed, 'line_total' => $billed]);

        $insurer = Insurer::query()->where('code', 'WARBA')->firstOrFail();
        $plan = $insurer->plans()->where('code', 'WARBA-GOLD')->firstOrFail();
        $policy = PatientInsurancePolicy::create([
            'patient_id' => $f['patient']->id, 'insurer_id' => $insurer->id, 'plan_id' => $plan->id,
            'policy_number' => 'TEST-001', 'status' => PatientInsurancePolicy::STATUS_ACTIVE,
            'is_primary' => true, 'priority' => 1,
            'effective_from' => now()->subMonth()->toDateString(), 'effective_until' => now()->addYear()->toDateString(),
        ]);

        $service = app(InsuranceService::class);
        $claim = $service->createClaimFromVisit($visit, $policy, $f['user']);
        $service->transition($claim, InsuranceClaim::STATUS_SUBMITTED, $f['user'], 'submit');
        $service->transition($claim, InsuranceClaim::STATUS_UNDER_REVIEW, $f['user']);
        $service->transition($claim, InsuranceClaim::STATUS_APPROVED, $f['user'], null, ['approved_amount' => $approved]);

        return [$claim->refresh(), $f['user']];
    }

    private function bal(string $code): float
    {
        return $this->account($code)->balanceAt();
    }

    public function test_paid_claim_keeps_reclass_and_balances_are_correct_by_account(): void
    {
        [$claim, $user] = $this->approvedClaim(billed: 100.0, approved: 100.0);

        // After approval: revenue recognised once, full receivable reclassed to insurer.
        $this->assertEqualsWithDelta(100.0, $this->bal('4110'), 0.001, 'Revenue recognised once');
        $this->assertEqualsWithDelta(0.0, $this->bal('1140'), 0.001, 'Patient AR fully moved to insurer');
        $this->assertEqualsWithDelta(100.0, $this->bal('1145'), 0.001, 'Insurer AR opened');

        // Insurer pays in full → claim auto-transitions to PAID.
        app(InsuranceService::class)->recordInsurerPayment($claim, 100.0, 'transfer', 'WIRE-1', null, $user);
        $claim->refresh();
        $this->assertSame(InsuranceClaim::STATUS_PAID, $claim->status);

        // BUG 1 regression: the 'paid' transition must NOT reverse the reclass.
        $reclass = JournalEntry::query()
            ->where('source_type', InsuranceClaim::class)->where('source_id', $claim->id)
            ->where('status', JournalEntry::STATUS_POSTED)->first();
        $this->assertNotNull($reclass, 'Reclass entry must remain posted after paid');

        // Final balances by account: insurer AR settled, patient AR clear,
        // revenue unchanged, cash received — and books balance.
        $this->assertEqualsWithDelta(0.0, $this->bal('1145'), 0.001, 'Insurer AR settled by payment');
        $this->assertEqualsWithDelta(0.0, $this->bal('1140'), 0.001, 'Patient AR still clear');
        $this->assertEqualsWithDelta(100.0, $this->bal('4110'), 0.001, 'Revenue NOT double-counted');
        $this->assertEqualsWithDelta(100.0, $this->bal('1120'), 0.001, 'Cash received via transfer');
        $this->assertBooksBalance();
    }

    public function test_partial_payment_ages_insurer_balance_and_clears_patient(): void
    {
        [$claim, $user] = $this->approvedClaim(billed: 100.0, approved: 100.0);

        // Insurer pays only 70 of the 100 approved.
        app(InsuranceService::class)->recordInsurerPayment($claim, 70.0, 'transfer', 'WIRE-2', null, $user);

        $aging = app(AgingService::class)->accountsReceivable(now()->toDateString());

        // Insurer carries the remaining 30; the patient row is fully clear.
        $insurerRow = collect($aging['rows'])->first(fn ($r) => str_contains($r['label'], 'insurer'));
        $this->assertNotNull($insurerRow, 'Insurer aging row must exist');
        $this->assertEqualsWithDelta(30.0, $insurerRow['total'], 0.001, 'Insurer balance aged, not lost');
        $this->assertEqualsWithDelta(30.0, $aging['totals']['total'], 0.001, 'Only the insurer 30 is open');
        $this->assertEqualsWithDelta(0.0, $this->bal('1140'), 0.001, 'Patient AR fully reclassed/clear');
    }
}
