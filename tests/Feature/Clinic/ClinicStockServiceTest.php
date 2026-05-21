<?php

namespace Tests\Feature\Clinic;

use App\Models\ClinicItemStock;
use App\Models\ClinicStockMovement;
use App\Services\Clinic\ClinicStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class ClinicStockServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected ClinicStockService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(ClinicStockService::class);
        config(['clinic.inventory_enabled' => true]);
    }

    public function test_restock_increases_stock_and_logs_movement(): void
    {
        $f = $this->seedClinicFixtures();
        $item = $this->makeClinicItem(['default_cost' => 1.000]);
        $this->makeStock($item, 0);

        $stock = $this->svc->restock(
            branchId: $f['branch']->id,
            item: $item,
            qtyStockUnits: null,
            qtyBase: 50,
            performedBy: 0,
            notes: 'initial stock'
        );

        $this->assertEqualsWithDelta(50.0, (float) $stock->qty_on_hand_base, 0.001);

        $movement = ClinicStockMovement::where('clinic_item_id', $item->id)->latest('id')->first();
        $this->assertSame('restock', $movement->type);
        $this->assertEqualsWithDelta(50.0, (float) $movement->qty_change_base, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $movement->before_qty_base, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $movement->after_qty_base, 0.001);
    }

    public function test_consume_decreases_stock_and_logs_movement(): void
    {
        $f = $this->seedClinicFixtures();
        $item = $this->makeClinicItem();
        $this->makeStock($item, 100);

        $stock = $this->svc->consume($f['branch']->id, $item, 12.5);

        $this->assertEqualsWithDelta(87.5, (float) $stock->qty_on_hand_base, 0.001);

        $movement = ClinicStockMovement::where('clinic_item_id', $item->id)->latest('id')->first();
        $this->assertSame('consume', $movement->type);
        // qty_change_base is stored negative for consume
        $this->assertEqualsWithDelta(-12.5, (float) $movement->qty_change_base, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $movement->before_qty_base, 0.001);
        $this->assertEqualsWithDelta(87.5, (float) $movement->after_qty_base, 0.001);
    }

    public function test_consume_throws_when_insufficient_stock(): void
    {
        $f = $this->seedClinicFixtures();
        $item = $this->makeClinicItem();
        $this->makeStock($item, 5);  // only 5 on hand

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->svc->consume($f['branch']->id, $item, 10);
    }

    public function test_consume_zero_is_no_op(): void
    {
        $f = $this->seedClinicFixtures();
        $item = $this->makeClinicItem();
        $this->makeStock($item, 50);

        $countBefore = ClinicStockMovement::count();
        $this->svc->consume($f['branch']->id, $item, 0);

        $this->assertSame($countBefore, ClinicStockMovement::count(),
            'Zero-qty consume must not produce a movement');
        $this->assertEqualsWithDelta(50.0, (float) ClinicItemStock::where('clinic_item_id', $item->id)->first()->qty_on_hand_base, 0.001);
    }

    public function test_available_base_reports_current_balance(): void
    {
        $f = $this->seedClinicFixtures();
        $item = $this->makeClinicItem();
        $this->makeStock($item, 25);

        $this->assertEqualsWithDelta(25.0, $this->svc->availableBase($f['branch']->id, $item->id), 0.001);

        $this->svc->consume($f['branch']->id, $item, 7);
        $this->assertEqualsWithDelta(18.0, $this->svc->availableBase($f['branch']->id, $item->id), 0.001);
    }

    public function test_shortages_for_requirements_returns_per_item_gaps(): void
    {
        $f = $this->seedClinicFixtures();
        $itemA = $this->makeClinicItem();
        $itemB = $this->makeClinicItem();
        $this->makeStock($itemA, 5);
        $this->makeStock($itemB, 100);

        $shortages = $this->svc->shortagesForRequirements($f['branch']->id, [
            ['clinic_item_id' => $itemA->id, 'qty_base' => 10],
            ['clinic_item_id' => $itemB->id, 'qty_base' => 50],
        ]);

        // Only itemA is short (need 10, have 5)
        $this->assertCount(1, $shortages);
        $this->assertSame($itemA->id, $shortages[0]['clinic_item_id']);
        $this->assertEqualsWithDelta(5.0, $shortages[0]['missing'], 0.001);
    }

    public function test_disabled_inventory_makes_calls_no_op(): void
    {
        config(['clinic.inventory_enabled' => false]);
        $f = $this->seedClinicFixtures();
        $item = $this->makeClinicItem();

        // restock with disabled inventory returns stock row but doesn't add movement
        $countBefore = ClinicStockMovement::count();
        $this->svc->restock($f['branch']->id, $item, null, 50);
        $this->assertSame($countBefore, ClinicStockMovement::count());
    }
}
