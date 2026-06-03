<?php

namespace Tests\Feature;

use App\Models\ClinicCoupon;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\ClinicPromotion;
use App\Models\Visit;
use App\Services\Clinic\ClinicPromotionService;
use App\Services\Clinic\VisitDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_coupon_is_capped_by_max_discount(): void
    {
        $c = ClinicCoupon::create([
            'code' => 'P10', 'discount_type' => 'percent', 'discount_value' => 10,
            'min_subtotal' => 0, 'max_discount' => 5, 'is_active' => true,
        ]);

        $this->assertSame(5.0, $c->discountFor(200));  // 20 capped to 5
        $this->assertSame(3.0, $c->discountFor(30));   // 3 under the cap
    }

    public function test_amount_coupon_never_exceeds_subtotal(): void
    {
        $c = ClinicCoupon::create(['code' => 'A50', 'discount_type' => 'amount', 'discount_value' => 50, 'min_subtotal' => 0, 'is_active' => true]);
        $this->assertSame(30.0, $c->discountFor(30));
    }

    public function test_coupon_rejection_reasons(): void
    {
        $minSpend = ClinicCoupon::create(['code' => 'MIN', 'discount_type' => 'amount', 'discount_value' => 5, 'min_subtotal' => 20, 'is_active' => true]);
        $this->assertNotNull($minSpend->rejectionReason(10));   // below min
        $this->assertNull($minSpend->rejectionReason(25));      // ok

        $inactive = ClinicCoupon::create(['code' => 'OFF', 'discount_type' => 'amount', 'discount_value' => 5, 'min_subtotal' => 0, 'is_active' => false]);
        $this->assertNotNull($inactive->rejectionReason(100));

        $expired = ClinicCoupon::create(['code' => 'OLD', 'discount_type' => 'amount', 'discount_value' => 5, 'min_subtotal' => 0, 'is_active' => true, 'ends_at' => now()->subDay()->toDateString()]);
        $this->assertNotNull($expired->rejectionReason(100));

        $maxed = ClinicCoupon::create(['code' => 'MAX', 'discount_type' => 'amount', 'discount_value' => 5, 'min_subtotal' => 0, 'is_active' => true, 'max_uses' => 1, 'uses_count' => 1]);
        $this->assertNotNull($maxed->rejectionReason(100));
    }

    public function test_visit_discount_resolves_manual_plus_coupon(): void
    {
        $c = ClinicCoupon::create(['code' => 'C5', 'discount_type' => 'amount', 'discount_value' => 5, 'min_subtotal' => 0, 'is_active' => true]);
        $visit = new Visit;
        $visit->forceFill(['discount_type' => 'percent', 'discount_value' => 10, 'coupon_id' => $c->id]);

        // 10% of 100 = 10, + coupon 5 = 15
        $this->assertSame(15.0, app(VisitDiscountService::class)->resolve($visit, 100));
        $this->assertTrue(app(VisitDiscountService::class)->hasInputs($visit));
    }

    public function test_visit_discount_capped_at_subtotal(): void
    {
        $visit = new Visit;
        $visit->forceFill(['discount_type' => 'amount', 'discount_value' => 999]);
        $this->assertSame(40.0, app(VisitDiscountService::class)->resolve($visit, 40));
    }

    public function test_promotion_specificity_beats_priority(): void
    {
        $svc = ClinicItem::create(['type' => 'service', 'name' => ['en' => 'Facial', 'ar' => 'Facial'], 'is_active' => true, 'default_cost' => 0, 'default_price' => 100]);

        // Type-wide promo with high priority...
        ClinicPromotion::create(['name' => 'All services', 'discount_type' => 'percent', 'discount_value' => 5, 'scope' => 'type', 'item_type' => 'service', 'priority' => 99, 'is_active' => true]);
        // ...vs a specific-item promo with low priority — specificity wins.
        ClinicPromotion::create(['name' => 'Facial deal', 'discount_type' => 'percent', 'discount_value' => 20, 'scope' => 'item', 'clinic_item_id' => $svc->id, 'priority' => 1, 'is_active' => true]);

        $best = app(ClinicPromotionService::class)->bestPromotion($svc);
        $this->assertSame('Facial deal', $best->name);
        // 20% of 100
        $this->assertSame(20.0, app(ClinicPromotionService::class)->discountForItem($svc, 100));
    }

    public function test_multi_item_promotion_matches_only_picked_items(): void
    {
        $a = ClinicItem::create(['type' => 'service', 'name' => ['en' => 'A', 'ar' => 'A'], 'is_active' => true, 'default_cost' => 0, 'default_price' => 100]);
        $b = ClinicItem::create(['type' => 'service', 'name' => ['en' => 'B', 'ar' => 'B'], 'is_active' => true, 'default_cost' => 0, 'default_price' => 100]);

        $promo = ClinicPromotion::create(['name' => 'Pick A', 'discount_type' => 'percent', 'discount_value' => 25, 'scope' => 'items', 'is_active' => true]);
        $promo->items()->sync([$a->id]);

        $svc = app(ClinicPromotionService::class);
        $this->assertSame(25.0, $svc->discountForItem($a, 100));  // in the set
        $this->assertSame(0.0, $svc->discountForItem($b, 100));   // not in the set
    }

    public function test_package_promotion_targets(): void
    {
        $svc = app(ClinicPromotionService::class);

        $p1 = ClinicPackage::create(['name' => ['en' => 'Bundle 1', 'ar' => 'B1'], 'is_active' => true, 'default_price' => 60]);
        $p2 = ClinicPackage::create(['name' => ['en' => 'Bundle 2', 'ar' => 'B2'], 'is_active' => true, 'default_price' => 80]);

        $promo = ClinicPromotion::create(['name' => 'Bundle 1 deal', 'discount_type' => 'percent', 'discount_value' => 10, 'scope' => 'packages', 'is_active' => true]);
        $promo->packages()->sync([$p1->id]);

        $this->assertSame(6.0, $svc->discountForPackage($p1, 60));  // 10% of 60
        $this->assertSame(0.0, $svc->discountForPackage($p2, 80));  // not targeted

        // An all-packages promotion covers everything.
        ClinicPromotion::create(['name' => 'All packages', 'discount_type' => 'amount', 'discount_value' => 5, 'scope' => 'all_packages', 'is_active' => true]);
        $this->assertSame(5.0, $svc->discountForPackage($p2, 80));
    }

    public function test_coupon_stacking_flag_defaults_on_and_is_settable(): void
    {
        $a = ClinicCoupon::create(['code' => 'STK', 'discount_type' => 'amount', 'discount_value' => 5, 'min_subtotal' => 0, 'is_active' => true]);
        $this->assertTrue($a->fresh()->stacks_with_promotions, 'Coupons stack with promotions by default');

        $b = ClinicCoupon::create(['code' => 'NOSTK', 'discount_type' => 'amount', 'discount_value' => 5, 'min_subtotal' => 0, 'is_active' => true, 'stacks_with_promotions' => false]);
        $this->assertFalse($b->fresh()->stacks_with_promotions);
    }

    public function test_expired_promotion_is_ignored(): void
    {
        $svc = ClinicItem::create(['type' => 'service', 'name' => ['en' => 'X', 'ar' => 'X'], 'is_active' => true, 'default_cost' => 0, 'default_price' => 50]);
        ClinicPromotion::create(['name' => 'Old', 'discount_type' => 'percent', 'discount_value' => 50, 'scope' => 'all', 'ends_at' => now()->subDay()->toDateString(), 'is_active' => true]);

        $this->assertSame(0.0, app(ClinicPromotionService::class)->discountForItem($svc, 50));
    }
}
