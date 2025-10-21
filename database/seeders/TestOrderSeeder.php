<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Block;
use App\Models\Branch;
use App\Models\City;
use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\CommerceOrderItemModifier;
use App\Models\CommercePayment;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Partner;
use App\Models\Service;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestOrderSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Location basics (create if missing)
        $state = State::firstOrCreate(
            ['slug' => 'kuwait'],
            ['name' => ['en' => 'Kuwait', 'ar' => 'الكويت'], 'is_active' => true]
        );

        $city = City::firstOrCreate(
            ['slug' => 'kuwait-city'],
            [
                'state_id' => $state->id,
                'name' => ['en' => 'Kuwait City', 'ar' => 'مدينة الكويت'],
                'latitude' => 29.3759,
                'longitude' => 47.9774,
                'is_active' => true,
            ]
        );

        $block = Block::firstOrCreate(
            ['code' => 'A1', 'city_id' => $city->id],
            ['name' => ['en' => 'Block A1', 'ar' => 'القطعة A1'], 'is_active' => true]
        );

        // 2) Service + Partner + Branch
        $service = Service::firstOrCreate(
            ['slug' => 'food'],
            ['name' => ['en' => 'Food Delivery', 'ar' => 'توصيل طعام'], 'icon' => 'utensils', 'is_active' => true]
        );

        $partner = Partner::firstOrCreate(
            ['slug' => 'demo-partner'],
            ['name' => ['en' => 'Demo Partner', 'ar' => 'شريك تجريبي'], 'logo_path' => null, 'is_active' => true]
        );

        $branch = Branch::firstOrCreate(
            ['partner_id' => $partner->id, 'slug' => 'demo-branch'],
            [
                'name' => ['en' => 'Demo Branch', 'ar' => 'فرع تجريبي'],
                'phone' => '+96550000000',
                'address' => 'Some street, Kuwait City',
                'city_id' => $city->id,
                'block_id' => $block->id,
                'latitude' => 29.3759,
                'longitude' => 47.9774,
                'is_available' => true,
                'rating_avg' => 0,
                'rating_count' => 0,
                'delivery_fee' => 1.000,
                'min_order_amount' => 0,
                'open_for_delivery' => true,
                'open_for_pickup' => true,
            ]
        );
        // link the service to the branch
        if (! $branch->services()->where('service_id', $service->id)->exists()) {
            $branch->services()->attach($service->id);
        }

        // 3) Menu + Section + Item
        $menu = Menu::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => ['en' => 'Main Menu', 'ar' => 'القائمة الرئيسية']],
            ['description' => ['en' => 'Demo menu', 'ar' => 'قائمة تجريبية'], 'is_active' => true]
        );

        $section = MenuSection::firstOrCreate(
            ['menu_id' => $menu->id, 'name' => ['en' => 'Mains', 'ar' => 'الأطباق الرئيسية']],
            ['sort_order' => 1]
        );

        $item = MenuItem::firstOrCreate(
            ['menu_section_id' => $section->id, 'branch_id' => $branch->id, 'sku' => 'CB001'],
            [
                'name' => ['en' => 'Cheeseburger', 'ar' => 'تشيز برجر'],
                'description' => ['en' => 'Tasty burger', 'ar' => 'برجر لذيذ'],
                'image_path' => null,
                'price' => 2.500,
                'is_available' => true,
            ]
        );

        // 4) Modifiers (group + option) and attach to item
        $group = ModifierGroup::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => ['en' => 'Add-ons', 'ar' => 'إضافات']],
            ['is_required' => false, 'min_choices' => 0, 'max_choices' => 3]
        );

        $opt = ModifierOption::firstOrCreate(
            ['modifier_group_id' => $group->id, 'name' => ['en' => 'Extra Cheese', 'ar' => 'جبن إضافي']],
            ['price_delta' => 0.250, 'is_default' => false]
        );

        if (! $item->modifierGroups()->where('modifier_group_id', $group->id)->exists()) {
            $item->modifierGroups()->attach($group->id); // uses item_modifier_group
        }

        // 5) Customer & Address
        $user = User::firstOrCreate(
            ['email' => 'test.customer@example.com'],
            ['name' => 'Test Customer', 'password' => bcrypt('password')]
        );
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('customer');
        }

        $address = Address::firstOrCreate(
            ['user_id' => $user->id, 'block_id' => $block->id],
            [
                'state_id' => $state->id,
                'city_id' => $city->id,
                'street' => 'Test Street',
                'building' => '10',
                'floor' => '2',
                'apartment' => '5',
                'landmark' => 'Near park',
                'latitude' => 29.3760,
                'longitude' => 47.9775,
                'is_default' => true,
            ]
        );

        // 6) Create ONE order with 2x item + modifier
        $qty = 2;
        $unit = (float) $item->price;
        $modDelta = (float) $opt->price_delta;

        $itemsTotal = ($unit * $qty) + ($modDelta * $qty); // price + modifier for each item
        $deliveryFee = (float) ($branch->delivery_fee ?? 0);
        $grand = $itemsTotal + $deliveryFee;

        $order = CommerceOrder::create([
            'code' => 'CO-SEED-'.Str::upper(Str::random(6)),
            'service_id' => $service->id,
            'partner_id' => $partner->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'type' => 'delivery',
            'status' => 'placed',
            'address_id' => $address->id,
            'snapshot_partner' => ['name' => $partner->name],
            'snapshot_branch' => ['name' => $branch->name],
            'items_total' => $itemsTotal,
            'delivery_fee' => $deliveryFee,
            'grand_total' => $grand,
            'currency' => 'KWD',
            'notes' => 'Seeded test order',
            'placed_at' => now(),
        ]);

        $orderItem = CommerceOrderItem::create([
            'commerce_order_id' => $order->id,
            'menu_item_id' => $item->id,
            'name' => $item->getTranslation('name', app()->getLocale()),
            'sku' => $item->sku,
            'unit_price' => $unit,
            'quantity' => $qty,
            'subtotal' => $unit * $qty,
        ]);

        CommerceOrderItemModifier::create([
            'commerce_order_item_id' => $orderItem->id,
            'modifier_group_id' => $group->id,
            'modifier_option_id' => $opt->id,
            'group_name' => $group->getTranslation('name', app()->getLocale()),
            'option_name' => $opt->getTranslation('name', app()->getLocale()),
            'price_delta' => $modDelta,
        ]);

        // 7) Payment (cash pending) — simple for testing
        CommercePayment::create([
            'commerce_order_id' => $order->id,
            'gateway_account_id' => null,
            'method' => 'cash',
            'status' => 'pending',
            'amount' => $grand,
            'currency' => 'KWD',
        ]);

        $this->command->info('Seeded order: '.$order->code.' (branch '.$branch->id.')');
    }
}
