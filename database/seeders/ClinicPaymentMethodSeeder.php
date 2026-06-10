<?php

namespace Database\Seeders;

use App\Models\ClinicPaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Seeds the GLOBAL default payment methods (partner_id = null, branch_id = null)
 * that every clinic falls back to. Idempotent: keyed on (key + null + null).
 * Clinics/branches add their own rows via the Filament admin to override.
 */
class ClinicPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // key,        label,        type,     requires_reference, sort_order
            ['cash',      'Cash',       'manual', false, 10],
            ['knet',      'KNET',       'manual', true,  20],
            ['card',      'Card',       'manual', true,  30],
            ['transfer',  'Transfer',   'manual', true,  40],
            ['insurance', 'Insurance',  'manual', true,  50],
            ['link',      'Payment Link', 'online', true, 60],
        ];

        foreach ($defaults as [$key, $label, $type, $requiresRef, $sort]) {
            ClinicPaymentMethod::updateOrCreate(
                [
                    'key' => $key,
                    'partner_id' => null,
                    'branch_id' => null,
                ],
                [
                    'label' => $label,
                    'type' => $type,
                    'requires_reference' => $requiresRef,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );
        }
    }
}
