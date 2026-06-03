<?php

namespace Database\Seeders;

use App\Models\ClinicCoupon;
use App\Models\ClinicItem;
use App\Models\ClinicPromotion;
use Illuminate\Database\Seeder;

/**
 * Demo coupons + a time-bound catalog promotion so the discount features are
 * visible in the pitch. Idempotent (keyed by code / name).
 */
class ClinicDiscountDemoSeeder extends Seeder
{
    public function run(): void
    {
        ClinicCoupon::updateOrCreate(['code' => 'WELCOME10'], [
            'name' => 'New patient — 10% off',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_subtotal' => 0,
            'max_discount' => 10,           // cap the percent at 10 KWD
            'is_active' => true,
        ]);

        ClinicCoupon::updateOrCreate(['code' => 'SAVE5'], [
            'name' => '5 KWD off',
            'discount_type' => 'amount',
            'discount_value' => 5,
            'min_subtotal' => 20,
            'is_active' => true,
        ]);

        // A service-wide promotion: 15% off all services this month.
        ClinicPromotion::updateOrCreate(['name' => 'Services — 15% this month'], [
            'discount_type' => 'percent',
            'discount_value' => 15,
            'scope' => 'type',
            'item_type' => 'service',
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'priority' => 10,
            'is_active' => true,
        ]);

        // A specific-item promotion if HydraFacial exists (beats the type-wide one).
        $hydra = ClinicItem::where('type', 'service')->get()
            ->first(fn ($i) => str_contains(mb_strtolower($i->localized_name), 'hydrafacial'));
        if ($hydra) {
            ClinicPromotion::updateOrCreate(['name' => 'HydraFacial launch — 20%'], [
                'discount_type' => 'percent',
                'discount_value' => 20,
                'scope' => 'item',
                'clinic_item_id' => $hydra->id,
                'starts_at' => now()->startOfMonth()->toDateString(),
                'ends_at' => now()->endOfMonth()->toDateString(),
                'priority' => 20,
                'is_active' => true,
            ]);
        }

        $this->command?->info('ClinicDiscountDemoSeeder: 2 coupons + promotions seeded.');
    }
}
