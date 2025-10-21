<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'name' => ['en' => 'Welcome 10%', 'ar' => 'خصم ترحيبي 10%'],
                'discount_type' => 'percent',
                'discount_percent' => 10,
                'min_order_amount' => 0,
                'allowed_order_type' => 'any',
                'apply_to' => 'matching_items',
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'KD-1.500'],
            [
                'name' => ['en' => 'Flat 1.500 KD', 'ar' => 'خصم ثابت 1.500 د.ك'],
                'discount_type' => 'amount',
                'discount_amount' => 1.500,
                'min_order_amount' => 5.000,
                'allowed_order_type' => 'delivery',
                'apply_to' => 'order',
                'max_discount_amount' => 1.500,
                'is_active' => true,
            ]
        );
    }
}
