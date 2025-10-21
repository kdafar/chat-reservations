<?php

namespace Database\Seeders;

use App\Models\Gateway;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class GatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks to prevent errors during seeding
        Schema::disableForeignKeyConstraints();

        $gateways = [
            [
                'driver' => 'myfatoorah',
                'name' => [
                    'en' => 'MyFatoorah',
                    'ar' => 'ماي فاتورة',
                ],
                'description' => [
                    'en' => 'Pay securely with KNET, Visa, or MasterCard.',
                    'ar' => 'دفع آمن. كي نت / فيزا / ماستركارد.',
                ],
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'driver' => 'tap',
                'name' => [
                    'en' => 'Tap',
                    'ar' => 'تاب',
                ],
                'description' => [
                    'en' => 'Pay with any debit or credit card.',
                    'ar' => 'ادفع بأي بطاقة ائتمان أو خصم مباشر.',
                ],
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'driver' => 'cash',
                'name' => [
                    'en' => 'Cash',
                    'ar' => 'نقداً',
                ],
                'description' => [
                    'en' => 'Pay with cash when your order arrives.',
                    'ar' => 'ادفع نقدًا عند وصول طلبك.',
                ],
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'driver' => 'stripe',
                'name' => [
                    'en' => 'Stripe',
                    'ar' => 'سترايب',
                ],
                'description' => [
                    'en' => 'Pay with international credit cards.',
                    'ar' => 'الدفع ببطاقات الائتمان الدولية.',
                ],
                'is_system' => true,
                'is_active' => false, // Inactive by default as an example
            ],
        ];

        foreach ($gateways as $gateway) {
            // Use updateOrCreate to avoid creating duplicates if the seeder is run again.
            Gateway::updateOrCreate(
                ['driver' => $gateway['driver']], // Unique key to check against
                $gateway // Data to insert or update
            );
        }

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }
}
