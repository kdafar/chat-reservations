<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Gateway;
use App\Models\GatewayAccount;
use Illuminate\Database\Seeder;

class GatewayAccountSeeder extends Seeder
{
    public function run(): void
    {
        $currency = 'KWD';

        // Real test token you want everywhere (system fallback + fill missing)
        $testToken = 'SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx';

        // 1) Gateways
        $myfatoorah = Gateway::firstOrCreate(
            ['driver' => 'myfatoorah'],
            [
                'name' => ['en' => 'MyFatoorah', 'ar' => 'ماي فاتورة'],
                'is_system' => true,
                'is_active' => true,
                'logo_path' => 'gateways/myfatoorah.png',
                'description' => ['en' => 'KNET / Visa / MasterCard', 'ar' => 'كي نت / فيزا / ماستركارد'],
            ]
        );

        $cash = Gateway::firstOrCreate(
            ['driver' => 'cash'],
            [
                'name' => ['en' => 'Cash', 'ar' => 'نقداً'],
                'is_system' => true,
                'is_active' => true,
                'logo_path' => 'gateways/cash.png',
                'description' => ['en' => 'Manual payment', 'ar' => 'دفع يدوي'],
            ]
        );

        $knet = Gateway::firstOrCreate(
            ['driver' => 'knet'],
            [
                'name' => ['en' => 'KNET', 'ar' => 'كي نت'],
                'is_system' => true,
                'is_active' => true,
                'logo_path' => 'gateways/knet.png',
                'description' => ['en' => 'POS payment', 'ar' => 'دفع نقاط البيع'],
            ]
        );

        $visa = Gateway::firstOrCreate(
            ['driver' => 'visa'],
            [
                'name' => ['en' => 'Credit Card', 'ar' => 'بطاقة'],
                'is_system' => true,
                'is_active' => true,
                'logo_path' => 'gateways/visa.png',
                'description' => ['en' => 'POS payment', 'ar' => 'دفع نقاط البيع'],
            ]
        );

        $mfCredentials = [
            'api_key' => $testToken,
            'mode' => 'test',
            'country_iso' => 'KWT',
        ];

        // 2) FORCE system MyFatoorah to be correct (this fixes your mf_test_XXXX)
        $sys = GatewayAccount::updateOrCreate(
            [
                'gateway_id' => $myfatoorah->id,
                'owner_type' => 'system',
                'currency' => $currency,
            ],
            [
                'display_name' => 'System MyFatoorah (Test)',
                'partner_id' => null,
                'branch_id' => null,
                'service_id' => null,
                'is_active' => true,
                'is_default' => true,
                'credentials' => $mfCredentials,
            ]
        );

        // 3) Manual method accounts (branch scoped) with REQUIRED credentials.method
        $manual = [
            'cash' => ['gateway_id' => $cash->id, 'label' => 'Cash'],
            'knet' => ['gateway_id' => $knet->id, 'label' => 'KNET (POS)'],
            'visa' => ['gateway_id' => $visa->id, 'label' => 'Credit Card (POS)'],
            'link' => ['gateway_id' => $cash->id, 'label' => 'Payment Link (Online)'], // link triggers myfatoorah in code
        ];

        $created = 0;
        $updated = 0;
        $disabled = 0;

        Branch::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunk(200, function ($branches) use ($currency, $manual, &$created, &$updated, &$disabled) {
                foreach ($branches as $b) {
                    $branchId = (int) $b->id;

                    foreach ($manual as $method => $meta) {
                        $gatewayId = (int) $meta['gateway_id'];
                        $label = (string) $meta['label'];

                        // Find by credentials.method if exists
                        $existing = GatewayAccount::query()
                            ->where('owner_type', 'branch')
                            ->where('branch_id', $branchId)
                            ->where('currency', $currency)
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(credentials, '$.method')) = ?", [$method])
                            ->orderByDesc('id')
                            ->first();

                        if ($existing) {
                            $creds = (array) ($existing->credentials ?? []);
                            $creds['method'] = $method;
                            $creds['kind'] = $creds['kind'] ?? 'manual';

                            $existing->update([
                                'gateway_id' => $gatewayId,
                                'display_name' => $label,
                                'is_active' => true,
                                'is_default' => false,
                                'credentials' => $creds,
                            ]);

                            $updated++;
                        } else {
                            // If old rows exist without credentials.method, try to reuse them by driver match:
                            // e.g. gateway driver cash/knet/visa could exist but missing credentials.method.
                            $reuse = GatewayAccount::query()
                                ->where('owner_type', 'branch')
                                ->where('branch_id', $branchId)
                                ->where('currency', $currency)
                                ->where('gateway_id', $gatewayId)
                                ->orderByDesc('id')
                                ->first();

                            if ($reuse && empty(data_get($reuse->credentials, 'method'))) {
                                $creds = (array) ($reuse->credentials ?? []);
                                $creds['method'] = $method;
                                $creds['kind'] = $creds['kind'] ?? 'manual';

                                $reuse->update([
                                    'display_name' => $label,
                                    'is_active' => true,
                                    'is_default' => false,
                                    'credentials' => $creds,
                                ]);

                                $updated++;
                            } else {
                                GatewayAccount::create([
                                    'gateway_id' => $gatewayId,
                                    'owner_type' => 'branch',
                                    'partner_id' => null,
                                    'branch_id' => $branchId,
                                    'service_id' => null,
                                    'display_name' => $label,
                                    'currency' => $currency,
                                    'is_active' => true,
                                    'is_default' => false,
                                    'credentials' => [
                                        'method' => $method,
                                        'kind' => 'manual',
                                    ],
                                ]);

                                $created++;
                            }
                        }

                        // Deduplicate: keep newest active, disable older duplicates for this method
                        $ids = GatewayAccount::query()
                            ->where('owner_type', 'branch')
                            ->where('branch_id', $branchId)
                            ->where('currency', $currency)
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(credentials, '$.method')) = ?", [$method])
                            ->orderByDesc('id')
                            ->pluck('id')
                            ->all();

                        if (count($ids) > 1) {
                            $keepId = (int) $ids[0];
                            $dropIds = array_slice($ids, 1);

                            $disabled += GatewayAccount::query()
                                ->whereIn('id', $dropIds)
                                ->where('is_active', true)
                                ->update(['is_active' => false]);
                        }
                    }
                }
            });

        $this->command->info("GatewayAccountSeeder done. created={$created}, updated={$updated}, disabled_duplicates={$disabled}.");
        $this->command->info('System MyFatoorah was forced to use the real test key (no mf_test_XXXX).');
        $this->command->info('Branch manual methods now include credentials.method (cash/knet/visa/link).');
    }
}
