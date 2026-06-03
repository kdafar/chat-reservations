<?php

namespace Database\Seeders;

use App\Models\Insurance\Insurer;
use Illuminate\Database\Seeder;

/**
 * Seeds 8 Kuwait health insurers.
 *
 * Idempotent — upserts by `code`. Re-runs safely without duplicating rows.
 */
class InsurerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Kuwait health insurers...');

        $insurers = [
            [
                'code' => 'WARBA',
                'name' => 'Warba Insurance Company',
                'name_ar' => 'شركة وربة للتأمين',
                'tax_id' => 'KW-TAX-WARBA-001',
                'contact_email' => 'claims@warba.com.kw',
                'contact_phone' => '+965 1825 555',
                'address' => 'Sharq, Kuwait City, Kuwait',
                'payment_terms_days' => 30,
                'is_active' => true,
                'notes' => 'Established Kuwaiti insurer; standard 30-day net terms.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
            [
                'code' => 'GIG',
                'name' => 'Gulf Insurance Group (GIG)',
                'name_ar' => 'مجموعة الخليج للتأمين',
                'tax_id' => 'KW-TAX-GIG-002',
                'contact_email' => 'claims@gig.com.kw',
                'contact_phone' => '+965 1802 080',
                'address' => 'Salhia, Kuwait City, Kuwait',
                'payment_terms_days' => 45,
                'is_active' => true,
                'notes' => 'Regional group; longer 45-day reimbursement cycle.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
            [
                'code' => 'AHLEIA',
                'name' => 'Al Ahleia Insurance Company',
                'name_ar' => 'شركة الأهلية للتأمين',
                'tax_id' => 'KW-TAX-AHLEIA-003',
                'contact_email' => 'claims@ahleia.com.kw',
                'contact_phone' => '+965 2295 5555',
                'address' => 'Sharq, Kuwait City, Kuwait',
                'payment_terms_days' => 30,
                'is_active' => true,
                'notes' => 'One of the oldest Kuwaiti insurers.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
            [
                'code' => 'BUPA',
                'name' => 'Bupa Arabia',
                'name_ar' => 'بوبا العربية',
                'tax_id' => 'KW-TAX-BUPA-004',
                'contact_email' => 'claims@bupa.com.kw',
                'contact_phone' => '+965 2247 7777',
                'address' => 'Hawalli, Kuwait',
                'payment_terms_days' => 45,
                'is_active' => true,
                'notes' => 'International health-insurance specialist.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
            [
                'code' => 'NLGI',
                'name' => 'National Life & General Insurance',
                'name_ar' => 'الوطنية للتأمين العام والحياة',
                'tax_id' => 'KW-TAX-NLGI-005',
                'contact_email' => 'claims@nlgi.com.kw',
                'contact_phone' => '+965 1830 030',
                'address' => 'Qibla, Kuwait City, Kuwait',
                'payment_terms_days' => 30,
                'is_active' => true,
                'notes' => 'Mixed life & general insurance lines.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
            [
                'code' => 'SAUKWT',
                'name' => 'Saudi Kuwaiti Insurance',
                'name_ar' => 'الشركة السعودية الكويتية للتأمين',
                'tax_id' => 'KW-TAX-SAUKWT-006',
                'contact_email' => 'claims@saukwt.com.kw',
                'contact_phone' => '+965 2241 1235',
                'address' => 'Sharq, Kuwait City, Kuwait',
                'payment_terms_days' => 45,
                'is_active' => true,
                'notes' => 'Joint Saudi-Kuwaiti underwriter.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
            [
                'code' => 'TIJARI',
                'name' => 'Commercial Bank Insurance (Tijari)',
                'name_ar' => 'تأمين التجاري',
                'tax_id' => 'KW-TAX-TIJARI-007',
                'contact_email' => 'claims@tijari-insure.com.kw',
                'contact_phone' => '+965 1888 225',
                'address' => 'Mubarakiya, Kuwait City, Kuwait',
                'payment_terms_days' => 30,
                'is_active' => true,
                'notes' => 'Banking-affiliated insurer.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
            [
                'code' => 'BURUJ',
                'name' => 'Buruj Cooperative Insurance',
                'name_ar' => 'بروج للتأمين التعاوني',
                'tax_id' => 'KW-TAX-BURUJ-008',
                'contact_email' => 'claims@buruj.com.kw',
                'contact_phone' => '+965 2226 6700',
                'address' => 'Salmiya, Kuwait',
                'payment_terms_days' => 45,
                'is_active' => true,
                'notes' => 'Cooperative-model insurer; longer terms.',
                'meta' => ['country' => 'KW', 'currency' => 'KWD'],
            ],
        ];

        foreach ($insurers as $data) {
            Insurer::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('Seeded '.Insurer::count().' insurers.');
    }
}
