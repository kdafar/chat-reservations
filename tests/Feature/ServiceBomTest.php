<?php

namespace Tests\Feature;

use App\Models\ClinicItem;
use App\Services\Clinic\ServiceBomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The service bill-of-materials: a service explodes into the consumables it
 * uses; consumables/products stand for themselves; optional lines are not
 * auto-deducted. These rules drive stock deduction on visits and packages.
 */
class ServiceBomTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $type, string $name, float $cost = 0, bool $stockable = true): ClinicItem
    {
        return ClinicItem::create([
            'type' => $type,
            'name' => ['en' => $name, 'ar' => $name],
            'is_active' => true,
            // Only stock-bearing items deduct; services never hold stock.
            'is_stockable' => $type !== 'service' && $stockable,
            'default_cost' => $cost,
            'default_price' => 0,
        ]);
    }

    public function test_service_explodes_into_its_non_optional_components(): void
    {
        $vial = $this->item('consumable', 'Botox vial');
        $syringe = $this->item('consumable', 'Syringe');
        $gauze = $this->item('consumable', 'Gauze');
        $service = $this->item('service', 'Botox Full Face');

        $service->components()->createMany([
            ['component_item_id' => $vial->id, 'qty_base' => 2, 'is_optional' => false],
            ['component_item_id' => $syringe->id, 'qty_base' => 3, 'is_optional' => false],
            ['component_item_id' => $gauze->id, 'qty_base' => 1, 'is_optional' => true], // optional → skipped
        ]);

        $bom = app(ServiceBomService::class);

        $req = collect($bom->requirementsForItem($service->id, 1))
            ->keyBy('clinic_item_id')->map(fn ($r) => $r['qty_base']);

        $this->assertEqualsCanonicalizing([$vial->id, $syringe->id], $req->keys()->all());
        $this->assertSame(2.0, $req[$vial->id]);
        $this->assertSame(3.0, $req[$syringe->id]);
        $this->assertArrayNotHasKey($gauze->id, $req->all(), 'Optional component must not auto-deduct');
    }

    public function test_quantity_multiplies_through(): void
    {
        $vial = $this->item('consumable', 'Vial');
        $service = $this->item('service', 'Service');
        $service->components()->create(['component_item_id' => $vial->id, 'qty_base' => 2, 'is_optional' => false]);

        $req = app(ServiceBomService::class)->requirementsForItem($service->id, 3);

        $this->assertSame(6.0, $req[0]['qty_base']); // 2 per service × 3 services
    }

    public function test_non_service_stands_for_itself(): void
    {
        $glove = $this->item('consumable', 'Glove');

        $req = app(ServiceBomService::class)->requirementsForItem($glove->id, 5);

        $this->assertSame([['clinic_item_id' => $glove->id, 'qty_base' => 5.0]], $req);
    }

    public function test_non_stockable_items_are_not_deducted(): void
    {
        $bom = app(ServiceBomService::class);

        // A non-stockable consumable added directly → nothing to deduct.
        $supply = $this->item('consumable', 'Untracked supply', 0, stockable: false);
        $this->assertSame([], $bom->requirementsForItem($supply->id, 2));

        // A service whose only component is non-stockable → nothing to deduct.
        $tracked = $this->item('consumable', 'Tracked vial');
        $untracked = $this->item('consumable', 'Untracked gel', 0, stockable: false);
        $service = $this->item('service', 'Service');
        $service->components()->createMany([
            ['component_item_id' => $tracked->id, 'qty_base' => 1, 'is_optional' => false],
            ['component_item_id' => $untracked->id, 'qty_base' => 1, 'is_optional' => false],
        ]);
        $req = collect($bom->requirementsForItem($service->id, 1))->pluck('clinic_item_id')->all();
        $this->assertSame([$tracked->id], $req, 'Only the stockable component should be required');
    }

    public function test_material_cost_sums_non_optional_components(): void
    {
        $vial = $this->item('consumable', 'Vial', cost: 5.0);
        $syringe = $this->item('consumable', 'Syringe', cost: 1.0);
        $gauze = $this->item('consumable', 'Gauze', cost: 9.0);
        $service = $this->item('service', 'Service');
        $service->components()->createMany([
            ['component_item_id' => $vial->id, 'qty_base' => 2, 'is_optional' => false],   // 2×5 = 10
            ['component_item_id' => $syringe->id, 'qty_base' => 3, 'is_optional' => false], // 3×1 = 3
            ['component_item_id' => $gauze->id, 'qty_base' => 1, 'is_optional' => true],    // optional → excluded
        ]);

        $bom = app(ServiceBomService::class);
        $this->assertSame(13.0, $bom->materialCost($service->id, 1));
        $this->assertSame(26.0, $bom->materialCost($service->id, 2));
        $this->assertSame(0.0, $bom->materialCost($vial->id), 'Non-services have no BOM material cost');
    }

    public function test_explode_merges_duplicate_components_across_lines(): void
    {
        $vial = $this->item('consumable', 'Vial');
        $serviceA = $this->item('service', 'A');
        $serviceB = $this->item('service', 'B');
        $serviceA->components()->create(['component_item_id' => $vial->id, 'qty_base' => 2, 'is_optional' => false]);
        $serviceB->components()->create(['component_item_id' => $vial->id, 'qty_base' => 1, 'is_optional' => false]);

        $req = app(ServiceBomService::class)->explode([
            ['clinic_item_id' => $serviceA->id, 'qty' => 1],
            ['clinic_item_id' => $serviceB->id, 'qty' => 2],
        ]);

        // serviceA: 2×1 ; serviceB: 1×2 → total 4 vials, single merged line
        $this->assertSame([['clinic_item_id' => $vial->id, 'qty_base' => 4.0]], $req);
    }
}
