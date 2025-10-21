<?php

namespace Database\Seeders;

use App\Models\Promotion;
use App\Models\PromotionAction;
use App\Models\PromotionCondition;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Buy 2 get 1 (item_id=10)
        $p1 = Promotion::create([
            'service_id' => 1, 'partner_id' => 3, 'branch_id' => null,
            'title' => ['en' => 'Buy 2 Get 1 Free (Margherita Pizza)', 'ar' => 'اشتري 2 واحصل على 1 مجانا (بيتزا مارجريتا)'],
            'summary' => ['en' => 'Auto-applies on same item', 'ar' => 'تطبيق تلقائي على نفس الصنف'],
            'type' => 'item', 'status' => 'active', 'priority' => 200, 'stack_behavior' => 'exclusive',
            'auto_apply' => true, 'channels' => ['web'],
        ]);
        PromotionCondition::create([
            'promotion_id' => $p1->id, 'condition_type' => 'bxgy_same_item',
            'payload' => ['item_id' => 10, 'buy_qty' => 2, 'get_qty' => 1, 'repeat' => true],
        ]);
        PromotionAction::create([
            'promotion_id' => $p1->id, 'action_type' => 'give_free_item',
            'payload' => ['item_id' => 10, 'qty' => 1],
        ]);

        // 2) Cart threshold ≥ KD 7 → free delivery
        $p2 = Promotion::create([
            'service_id' => 1, 'partner_id' => 3, 'branch_id' => null,
            'title' => ['en' => 'Free Delivery over KD 7', 'ar' => 'توصيل مجاني عند 7 د.ك'],
            'summary' => ['en' => 'Auto-applies at checkout', 'ar' => 'تطبيق تلقائي عند الدفع'],
            'type' => 'cart', 'status' => 'active', 'priority' => 120, 'stack_behavior' => 'stack',
            'auto_apply' => true, 'channels' => ['web'],
        ]);
        PromotionCondition::create([
            'promotion_id' => $p2->id, 'condition_type' => 'cart_min_subtotal',
            'payload' => ['amount' => 7.0],
        ]);
        PromotionAction::create([
            'promotion_id' => $p2->id, 'action_type' => 'free_delivery', 'payload' => new \stdClass,
        ]);

        // 3) Bundle: Burger + Fries + Drink for KD 3.900
        $p3 = Promotion::create([
            'service_id' => 1, 'partner_id' => 3, 'branch_id' => null,
            'title' => ['en' => 'Burger Combo KD 3.900', 'ar' => 'وجبة برجر 3.900 د.ك'],
            'summary' => ['en' => 'Burger+Fries+Drink at fixed price', 'ar' => 'برجر+بطاطس+مشروب بسعر ثابت'],
            'type' => 'bundle', 'status' => 'active', 'priority' => 180, 'stack_behavior' => 'exclusive',
            'auto_apply' => true, 'channels' => ['web'],
        ]);
        PromotionCondition::create([
            'promotion_id' => $p3->id, 'condition_type' => 'has_items_set',
            'payload' => ['items' => [
                ['item_id' => 101, 'qty' => 1], // burger
                ['item_id' => 102, 'qty' => 1], // fries
                ['item_id' => 103, 'qty' => 1], // drink
            ]],
        ]);
        PromotionAction::create([
            'promotion_id' => $p3->id, 'action_type' => 'bundle_price',
            'payload' => ['price' => 3.900, 'items' => [
                ['item_id' => 101, 'qty' => 1],
                ['item_id' => 102, 'qty' => 1],
                ['item_id' => 103, 'qty' => 1],
            ]],
        ]);
    }
}
