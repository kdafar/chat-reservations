<?php

namespace Tests\Feature\Clinic;

use App\Models\VisitCharge;
use App\Models\VisitItem;
use App\Models\VisitPackage;
use App\Services\Clinic\VisitCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class VisitCostingServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected VisitCostingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(VisitCostingService::class);

        // Ensure the financials flag is on so compute() actually runs.
        config(['clinic.visit_financials_enabled' => true]);
    }

    private function makeChargesAndItems(int $visitId, int $branchId, float $consultation, float $packagePrice, array $items = []): void
    {
        // Consultation charge
        VisitCharge::create([
            'visit_id' => $visitId,
            'branch_id' => $branchId,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => $consultation,
            'line_total' => $consultation,
        ]);

        // Package row (price only — not a stock requirement here)
        if ($packagePrice > 0) {
            $pkg = $this->makeClinicPackage(['default_price' => $packagePrice]);
            VisitPackage::create([
                'visit_id' => $visitId,
                'clinic_package_id' => $pkg->id,
                'branch_id' => $branchId,
                'qty' => 1,
                'unit_price_snapshot' => $packagePrice,
                'line_total' => $packagePrice,
            ]);
        }

        // Items: each row has cost + price + qty
        foreach ($items as $i) {
            $item = $this->makeClinicItem([
                'default_cost' => $i['cost'],
                'default_price' => $i['price'],
            ]);
            VisitItem::create([
                'visit_id' => $visitId,
                'clinic_item_id' => $item->id,
                'branch_id' => $branchId,
                'qty' => $i['qty'],
                'unit_cost_snapshot' => $i['cost'],
                'unit_price_snapshot' => $i['price'],
                'line_cost_total' => $i['qty'] * $i['cost'],
                'line_price_total' => $i['qty'] * $i['price'],
            ]);
        }
    }

    public function test_profit_formula_includes_packages_charges_and_items(): void
    {
        $visit = $this->makeVisit();
        $this->makeChargesAndItems(
            $visit->id,
            $visit->branch_id,
            consultation: 25.000,
            packagePrice: 60.000,
            items: [
                ['cost' => 2.000, 'price' => 5.000, 'qty' => 4],   // 8 cost / 20 price
                ['cost' => 1.000, 'price' => 3.000, 'qty' => 2],   // 2 cost / 6 price
            ]
        );

        $this->svc->compute($visit);
        $visit->refresh();

        // fees=25 (consultation), packages=60, items_cost=10, items_price=26
        $this->assertEqualsWithDelta(25.000, (float) $visit->fees_total, 0.001);
        $this->assertEqualsWithDelta(60.000, (float) $visit->packages_price_total, 0.001);
        $this->assertEqualsWithDelta(10.000, (float) $visit->items_cost_total, 0.001);
        $this->assertEqualsWithDelta(26.000, (float) $visit->items_price_total, 0.001);

        // profit = fees + packages + items_price - discount - items_cost
        //       = 25 + 60 + 26 - 0 - 10 = 101
        $this->assertEqualsWithDelta(101.000, (float) $visit->profit_total, 0.001);
    }

    public function test_no_packages_or_items_means_profit_equals_fees(): void
    {
        $visit = $this->makeVisit();
        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => 25.000,
            'line_total' => 25.000,
        ]);

        $this->svc->compute($visit);
        $visit->refresh();

        $this->assertEqualsWithDelta(25.000, (float) $visit->fees_total, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $visit->packages_price_total, 0.001);
        $this->assertEqualsWithDelta(25.000, (float) $visit->profit_total, 0.001);
    }

    public function test_discount_total_reduces_profit(): void
    {
        $visit = $this->makeVisit(['discount_total' => 10.000]);
        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => 30.000,
            'line_total' => 30.000,
        ]);

        $this->svc->compute($visit);
        $visit->refresh();

        // 30 fees − 10 discount = 20
        $this->assertEqualsWithDelta(20.000, (float) $visit->profit_total, 0.001);
        // discount_total preserved (NOT overwritten)
        $this->assertEqualsWithDelta(10.000, (float) $visit->discount_total, 0.001);
    }

    public function test_compute_does_not_overwrite_fees_from_doctor(): void
    {
        // Audit fix #2: compute() must NOT clobber fees_total from doctor.consultation_fee.
        // Doctor fee is 25 (from fixture), but no charges exist. fees_total should be 0.
        $visit = $this->makeVisit();
        // No VisitCharge rows
        $this->svc->compute($visit);
        $visit->refresh();

        $this->assertEqualsWithDelta(0.0, (float) $visit->fees_total, 0.001,
            'fees_total must come from visit_charges, NOT doctor->consultation_fee');
    }

    public function test_compute_is_feature_flagged(): void
    {
        config(['clinic.visit_financials_enabled' => false]);
        $visit = $this->makeVisit();
        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => 25.000,
            'line_total' => 25.000,
        ]);

        $this->svc->compute($visit);
        $visit->refresh();

        // With flag off, compute is a no-op — fees_total stays at default (0)
        $this->assertEqualsWithDelta(0.0, (float) $visit->fees_total, 0.001);
    }

    public function test_compute_stamps_metadata(): void
    {
        $visit = $this->makeVisit();
        VisitCharge::create([
            'visit_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee',
            'qty' => 1,
            'unit_price_snapshot' => 25.000,
            'line_total' => 25.000,
        ]);

        $this->svc->compute($visit);
        $visit->refresh();

        $this->assertNotNull($visit->computed_at);
        $this->assertNotNull($visit->computed_version);
    }

    public function test_remaining_balance_uses_full_bill(): void
    {
        $visit = $this->makeVisit();
        VisitCharge::create([
            'visit_id' => $visit->id, 'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee', 'qty' => 1,
            'unit_price_snapshot' => 25, 'line_total' => 25,
        ]);
        $pkg = $this->makeClinicPackage(['default_price' => 50.000]);
        VisitPackage::create([
            'visit_id' => $visit->id, 'clinic_package_id' => $pkg->id,
            'branch_id' => $visit->branch_id, 'qty' => 1,
            'unit_price_snapshot' => 50, 'line_total' => 50,
        ]);

        // Pay 30
        \App\Models\VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 30, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'services', 'paid_at' => now(),
        ]);

        $remaining = $this->svc->getRemainingBalance($visit);

        // Total due = 25 + 50 = 75; paid 30 → 45 remaining
        $this->assertEqualsWithDelta(45.0, $remaining, 0.001);
    }
}
