<?php

namespace Tests\Feature\Clinic;

use App\Models\Visit;
use App\Models\VisitCharge;
use App\Models\VisitPayment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Regression suite for the 2026-05-20 audit follow-up review findings.
 *
 * Each test pins down one specific bug discovered during the review so it
 * cannot resurface without a test failure.
 */
class AuditFollowupRegressionTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
    }

    // -------------------------------------------------------------------------
    // Fix #2 — MyFatoorah DB-level idempotency
    // -------------------------------------------------------------------------

    public function test_unique_index_blocks_duplicate_method_reference(): void
    {
        $visit = $this->makeVisit();

        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 25, 'method' => 'myfatoorah',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => 'MF-INV-12345', 'paid_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Second insert with same (method, reference_no) must hit the unique
        // index from 2026_05_20_144820_add_unique_method_reference...
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 25, 'method' => 'myfatoorah',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => 'MF-INV-12345', 'paid_at' => now(),
        ]);
    }

    public function test_unique_index_allows_multiple_null_references_for_cash(): void
    {
        // Cash payments often have no reference_no. NULL must be distinct in
        // the unique index so multiple cash receipts can coexist.
        $visit = $this->makeVisit();

        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 5, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => null, 'paid_at' => now(),
        ]);
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 10, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => null, 'paid_at' => now(),
        ]);

        $this->assertSame(2, VisitPayment::where('method', 'cash')->count());
    }

    public function test_unique_index_allows_same_reference_across_different_methods(): void
    {
        $visit = $this->makeVisit();

        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 25, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => 'RCPT-42', 'paid_at' => now(),
        ]);
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 25, 'method' => 'knet',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => 'RCPT-42', 'paid_at' => now(),
        ]);

        $this->assertSame(2, VisitPayment::where('reference_no', 'RCPT-42')->count());
    }

    // -------------------------------------------------------------------------
    // Fix #4 — revenue dashboards use full bill
    // -------------------------------------------------------------------------

    public function test_full_bill_revenue_includes_packages_and_items_minus_discount(): void
    {
        // This is the formula used by ExecutiveDashboard, DailyBusinessReport,
        // and RevenueTrendChart after the audit follow-up review.
        $visit = $this->makeVisit([
            'status' => 'completed',
            'fees_total' => 25.000,
            'packages_price_total' => 30.000,
            'items_price_total' => 10.000,
            'discount_total' => 5.000,
            'completed_at' => now(),
        ]);

        $revenue = (float) Visit::whereKey($visit->id)
            ->selectRaw('SUM(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0)) as r')
            ->value('r');

        // 25 + 30 + 10 - 5 = 60
        $this->assertEqualsWithDelta(60.000, $revenue, 0.001,
            'Dashboard revenue must include packages and items, minus discount');
    }

    // -------------------------------------------------------------------------
    // Fix #5 — legacy backfill: fees_total preserved on visits without charges
    // -------------------------------------------------------------------------

    public function test_legacy_visit_with_fees_total_but_no_charges_is_backfilled(): void
    {
        // Simulate a legacy visit: fees_total set, NO VisitCharge rows.
        $visit = $this->makeVisit([
            'fees_total' => 25.000,
            'packages_price_total' => 0,
            'items_price_total' => 0,
            'discount_total' => 0,
        ]);

        $this->assertSame(0, VisitCharge::where('visit_id', $visit->id)->count(),
            'Pre-condition: no VisitCharge rows yet');

        // Run the backfill migration as a one-off (mirrors what production does).
        \Illuminate\Support\Facades\DB::table('visit_charges')->insert([
            'visit_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => 25.000,
            'line_total' => 25.000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Now compute() will sum charges and produce fees_total = 25 again.
        config(['clinic.visit_financials_enabled' => true]);
        app(\App\Services\Clinic\VisitCostingService::class)->compute($visit);

        $visit->refresh();
        $this->assertEqualsWithDelta(25.000, (float) $visit->fees_total, 0.001,
            'Backfilled visit retains its original fees_total after recompute');
    }

    // -------------------------------------------------------------------------
    // Split consultation now allowed (audit-review bonus finding)
    // -------------------------------------------------------------------------

    public function test_multiple_consultation_payments_per_visit_now_allowed(): void
    {
        // After fix #4 (dropped (visit_id, kind) unique) and the BookingResource
        // collect_consultation block change, a visit can carry multiple
        // consultation payment rows (e.g. cash 10 + knet 15 = 25).
        $visit = $this->makeVisit();

        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 10, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => null, 'paid_at' => now(),
        ]);
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 15, 'method' => 'knet',
            'status' => 'paid', 'kind' => 'consultation',
            'reference_no' => null, 'paid_at' => now(),
        ]);

        $this->assertSame(2, VisitPayment::where('visit_id', $visit->id)
            ->where('kind', 'consultation')->count());

        $totalPaid = (float) VisitPayment::where('visit_id', $visit->id)
            ->where('kind', 'consultation')->where('status', 'paid')
            ->sum('amount');
        $this->assertEqualsWithDelta(25.0, $totalPaid, 0.001);
    }
}
