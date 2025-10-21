<?php

namespace Database\Seeders;

use App\Models\Gateway;
use App\Models\GatewayAccount;
use App\Models\Partner;
use Illuminate\Database\Seeder;

class PaymentsDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Gateways (system registry)
        $mf = Gateway::firstOrCreate(['driver' => 'myfatoorah'], ['name' => 'MyFatoorah', 'is_system' => true]);
        $tap = Gateway::firstOrCreate(['driver' => 'tap'], ['name' => 'Tap',         'is_system' => true]);
        $stripe = Gateway::firstOrCreate(['driver' => 'stripe'], ['name' => 'Stripe',      'is_system' => true]);
        $cash = Gateway::firstOrCreate(['driver' => 'cash'], ['name' => 'Cash',        'is_system' => true]);

        // 2) (Optional) create a demo partner if you don’t have one
        $partner = Partner::firstOrCreate(
            ['slug' => 'demo-partner'],
            ['name' => ['en' => 'Demo Partner', 'ar' => 'شريك تجريبي'], 'is_active' => true]
        );

        // 3) System default account for MyFatoorah (fallback for all partners)
        GatewayAccount::updateOrCreate(
            [
                'gateway_id' => $mf->id,
                'owner_type' => 'system',
                'partner_id' => null,
                'display_name' => 'System MyFatoorah',
            ],
            [
                'credentials' => [
                    // put your real creds later
                    'api_key' => 'mf_live_xxxxxxxxx',
                    'merchant_code' => 'xxxxx',
                    'profile_id' => 'xxxxx',
                ],
                'currency' => 'KWD',
                'is_active' => true,
                'is_default' => true,   // system default for MyFatoorah
            ]
        );

        // 4) Partner-specific default (overrides system default for this partner)
        GatewayAccount::updateOrCreate(
            [
                'gateway_id' => $mf->id,
                'owner_type' => 'partner',
                'partner_id' => $partner->id,
                'display_name' => 'Demo Partner MyFatoorah',
            ],
            [
                'credentials' => [
                    'api_key' => 'mf_live_partner_yyyyyyy',
                    'merchant_code' => 'ppppp',
                    'profile_id' => 'ppppp',
                ],
                'currency' => 'KWD',
                'is_active' => true,
                'is_default' => true,   // partner default for MyFatoorah
            ]
        );

        // 5) Another example: system Tap account (not default)
        GatewayAccount::updateOrCreate(
            [
                'gateway_id' => $tap->id,
                'owner_type' => 'system',
                'partner_id' => null,
                'display_name' => 'System Tap',
            ],
            [
                'credentials' => [
                    'secret_key' => 'sk_live_xxxxx',
                    'public_key' => 'pk_live_xxxxx',
                ],
                'currency' => 'KWD',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        // 6) Cash (no creds needed) – system-level
        GatewayAccount::updateOrCreate(
            [
                'gateway_id' => $cash->id,
                'owner_type' => 'system',
                'partner_id' => null,
                'display_name' => 'Cash on Delivery',
            ],
            [
                'credentials' => [],
                'currency' => 'KWD',
                'is_active' => true,
                'is_default' => false,
            ]
        );
    }
}
