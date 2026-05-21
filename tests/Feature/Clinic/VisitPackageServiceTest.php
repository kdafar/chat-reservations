<?php

namespace Tests\Feature\Clinic;

use App\Models\ClinicStockMovement;
use App\Models\VisitPackage;
use App\Models\VisitStockRequest;
use App\Services\Clinic\VisitPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class VisitPackageServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected VisitPackageService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(VisitPackageService::class);
        config([
            'clinic.packages_enabled' => true,
            'clinic.inventory_enabled' => true,
            'clinic.stock_requests_enabled' => true,
        ]);
    }

    public function test_apply_packages_only_creates_visit_package_row(): void
    {
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $pkg = $this->makeClinicPackage(['default_price' => 60.000]);

        $this->svc->applyPackagesOnly($visit, [
            ['clinic_package_id' => $pkg->id, 'qty' => 1],
        ]);

        $row = VisitPackage::where('visit_id', $visit->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($pkg->id, $row->clinic_package_id);
        $this->assertEqualsWithDelta(60.000, (float) $row->unit_price_snapshot, 0.001);
        $this->assertEqualsWithDelta(60.000, (float) $row->line_total, 0.001);
        $this->assertEqualsWithDelta(1.0, (float) $row->qty, 0.001);
    }

    public function test_apply_packages_only_does_not_touch_inventory(): void
    {
        // Audit fix #7: applyPackagesOnly MUST NOT consume stock or create stock requests.
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $item = $this->makeClinicItem(['default_cost' => 1.000]);
        $this->makeStock($item, 100);
        $pkg = $this->makeClinicPackage(['default_price' => 25.000], [
            ['item' => $item, 'qty' => 1],
        ]);

        $movementsBefore = ClinicStockMovement::count();
        $requestsBefore = VisitStockRequest::count();

        $this->svc->applyPackagesOnly($visit, [
            ['clinic_package_id' => $pkg->id, 'qty' => 1],
        ]);

        $this->assertSame($movementsBefore, ClinicStockMovement::count(),
            'applyPackagesOnly must NOT produce stock movements');
        $this->assertSame($requestsBefore, VisitStockRequest::count(),
            'applyPackagesOnly must NOT create stock requests');

        // Visit status must NOT change to awaiting_stock
        $visit->refresh();
        $this->assertNotSame('awaiting_stock', $visit->status);
    }

    public function test_quantity_multiplier_works(): void
    {
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $pkg = $this->makeClinicPackage(['default_price' => 20.000]);

        $this->svc->applyPackagesOnly($visit, [
            ['clinic_package_id' => $pkg->id, 'qty' => 3],
        ]);

        $row = VisitPackage::where('visit_id', $visit->id)->first();
        $this->assertEqualsWithDelta(3.0, (float) $row->qty, 0.001);
        $this->assertEqualsWithDelta(60.000, (float) $row->line_total, 0.001);
    }

    public function test_apply_packages_only_is_idempotent_increments_qty(): void
    {
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $pkg = $this->makeClinicPackage(['default_price' => 20.000]);

        $this->svc->applyPackagesOnly($visit, [['clinic_package_id' => $pkg->id, 'qty' => 1]]);
        $this->svc->applyPackagesOnly($visit, [['clinic_package_id' => $pkg->id, 'qty' => 2]]);

        $row = VisitPackage::where('visit_id', $visit->id)->where('clinic_package_id', $pkg->id)->first();
        $this->assertEqualsWithDelta(3.0, (float) $row->qty, 0.001, 'qty must accumulate');
        $this->assertEqualsWithDelta(60.000, (float) $row->line_total, 0.001);
    }

    public function test_requirements_for_packages_resolves_item_qtys(): void
    {
        $itemA = $this->makeClinicItem();
        $itemB = $this->makeClinicItem();
        $pkg = $this->makeClinicPackage(['default_price' => 30.000], [
            ['item' => $itemA, 'qty' => 2],
            ['item' => $itemB, 'qty' => 1],
        ]);

        $reqs = $this->svc->requirementsForPackages(
            $this->seedClinicFixtures()['branch']->id,
            [['clinic_package_id' => $pkg->id, 'qty' => 3]]
        );

        // 3 packages × 2 of itemA = 6, 3 × 1 of itemB = 3
        $this->assertCount(2, $reqs);
        $byItem = collect($reqs)->keyBy('clinic_item_id');
        $this->assertEqualsWithDelta(6.0, $byItem[$itemA->id]['qty_base'], 0.001);
        $this->assertEqualsWithDelta(3.0, $byItem[$itemB->id]['qty_base'], 0.001);
    }

    public function test_disabled_packages_makes_apply_a_noop(): void
    {
        config(['clinic.packages_enabled' => false]);
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $pkg = $this->makeClinicPackage(['default_price' => 60.000]);

        $this->svc->applyPackagesOnly($visit, [['clinic_package_id' => $pkg->id, 'qty' => 1]]);

        $this->assertSame(0, VisitPackage::count(), 'Disabled package flag means no rows created');
    }
}
