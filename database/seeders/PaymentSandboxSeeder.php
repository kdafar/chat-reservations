<?php

namespace Database\Seeders;

use App\Models\CommercePaymentPolicy;
use App\Models\Gateway;
use App\Models\GatewayAccount;
use Illuminate\Database\Seeder;

class PaymentSandboxSeeder extends Seeder
{
    public function run(): void
    {
        $mf = Gateway::firstOrCreate(['driver' => 'myfatoorah'], ['name' => 'MyFatoorah', 'is_system' => 1]);
        $tap = Gateway::firstOrCreate(['driver' => 'tap'], ['name' => 'Tap', 'is_system' => 1]);
        $st = Gateway::firstOrCreate(['driver' => 'stripe'], ['name' => 'Stripe', 'is_system' => 1]);
        $cod = Gateway::firstOrCreate(['driver' => 'cash'], ['name' => 'Cash', 'is_system' => 1]);

        $mfSys = GatewayAccount::updateOrCreate(
            ['gateway_id' => $mf->id, 'owner_type' => 'system', 'partner_id' => null, 'branch_id' => null, 'service_id' => null, 'display_name' => 'Sandbox MF'],
            ['credentials' => ['mode' => 'test', 'api_key' => 'mf_test_XXXX'], 'currency' => 'KWD', 'is_active' => 1, 'is_default' => 1]
        );
        $tapSys = GatewayAccount::updateOrCreate(
            ['gateway_id' => $tap->id, 'owner_type' => 'system', 'display_name' => 'Sandbox Tap'],
            ['credentials' => ['mode' => 'test', 'secret_key' => 'sk_test_XXXX'], 'currency' => 'KWD', 'is_active' => 1, 'is_default' => 0]
        );
        GatewayAccount::updateOrCreate(
            ['gateway_id' => $cod->id, 'owner_type' => 'system', 'display_name' => 'Cash on Delivery'],
            ['credentials' => [], 'currency' => 'KWD', 'is_active' => 1, 'is_default' => 0]
        );

        // Policies: < 20 KWD Tap, >= 20 KWD MF
        CommercePaymentPolicy::updateOrCreate(
            ['name' => 'Under 20 KWD → Tap'],
            ['priority' => 10, 'is_enabled' => true, 'conditions' => ['currency' => 'KWD', 'min_total' => 0, 'max_total' => 20],
                'action' => ['driver' => 'tap', 'owner_preference' => ['branch', 'partner', 'system'], 'allow_fallback' => true]]
        );
        CommercePaymentPolicy::updateOrCreate(
            ['name' => '20+ KWD → MyFatoorah'],
            ['priority' => 20, 'is_enabled' => true, 'conditions' => ['currency' => 'KWD', 'min_total' => 20],
                'action' => ['driver' => 'myfatoorah', 'owner_preference' => ['branch', 'partner', 'system'], 'allow_fallback' => true]]
        );
    }
}
