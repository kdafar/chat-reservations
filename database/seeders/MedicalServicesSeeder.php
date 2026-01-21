<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MedicalServicesSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['en' => 'General Practice', 'ar' => 'طب عام', 'icon' => '🩺'],
            ['en' => 'Pediatrics', 'ar' => 'طب الأطفال', 'icon' => '👶'],
            ['en' => 'Dermatology', 'ar' => 'الجلدية', 'icon' => '✨'],
            ['en' => 'Dentistry', 'ar' => 'طب الأسنان', 'icon' => '🦷'],
            ['en' => 'Cardiology', 'ar' => 'القلب', 'icon' => '❤️'],
            ['en' => 'Orthopedics', 'ar' => 'العظام', 'icon' => '🦴'],
            ['en' => 'Gynecology', 'ar' => 'النساء والولادة', 'icon' => '🤰'],
            ['en' => 'Ophthalmology', 'ar' => 'العيون', 'icon' => '👁️'],
            ['en' => 'ENT', 'ar' => 'أنف وأذن وحنجرة', 'icon' => '👂'],
            ['en' => 'Internal Medicine', 'ar' => 'الباطنية', 'icon' => '💊'],
            ['en' => 'Neurology', 'ar' => 'الأعصاب', 'icon' => '🧠'],
            ['en' => 'Psychiatry', 'ar' => 'الطب النفسي', 'icon' => '🧘'],
            ['en' => 'Nutrition', 'ar' => 'التغذية', 'icon' => '🍎'],
            ['en' => 'Physical Therapy', 'ar' => 'العلاج الطبيعي', 'icon' => '🤸'],
            ['en' => 'Radiology', 'ar' => 'الأشعة', 'icon' => '☢️'],
            ['en' => 'Laboratory', 'ar' => 'المختبر', 'icon' => '🧪'],
        ];

        foreach ($services as $svc) {
            Service::updateOrCreate(
                ['slug' => Str::slug($svc['en'])],
                [
                    'name' => ['en' => $svc['en'], 'ar' => $svc['ar']],
                    'icon' => $svc['icon'],
                    'is_active' => true,
                ]
            );
        }
    }
}
