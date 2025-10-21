<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KuwaitGeoSeeder extends Seeder
{
    public function run(): void
    {
        $govs = [
            [
                'name' => ['en' => 'Capital', 'ar' => 'العاصمة'],
                'slug' => 'capital',
                'cities' => [
                    ['en' => 'Kuwait City', 'ar' => 'مدينة الكويت'],
                    ['en' => 'Sharq', 'ar' => 'شرق'],
                    ['en' => 'Qibla', 'ar' => 'قبلة'],
                ],
            ],
            [
                'name' => ['en' => 'Hawalli', 'ar' => 'حولي'],
                'slug' => 'hawalli',
                'cities' => [
                    ['en' => 'Hawalli', 'ar' => 'حولي'],
                    ['en' => 'Salmiya', 'ar' => 'السالمية'],
                    ['en' => 'Jabriya', 'ar' => 'الجابرية'],
                ],
            ],
            [
                'name' => ['en' => 'Farwaniya', 'ar' => 'الفروانية'],
                'slug' => 'farwaniya',
                'cities' => [
                    ['en' => 'Farwaniya', 'ar' => 'الفروانية'],
                    ['en' => 'Khaitan', 'ar' => 'خيطان'],
                    ['en' => 'Jleeb Al-Shuyoukh', 'ar' => 'جليب الشيوخ'],
                ],
            ],
            [
                'name' => ['en' => 'Ahmadi', 'ar' => 'الأحمدي'],
                'slug' => 'ahmadi',
                'cities' => [
                    ['en' => 'Ahmadi', 'ar' => 'الأحمدي'],
                    ['en' => 'Fahaheel', 'ar' => 'الفحيحيل'],
                    ['en' => 'Mangaf', 'ar' => 'المنقف'],
                ],
            ],
            [
                'name' => ['en' => 'Al Jahra', 'ar' => 'الجهراء'],
                'slug' => 'jahra',
                'cities' => [
                    ['en' => 'Al Jahra', 'ar' => 'الجهراء'],
                    ['en' => 'Saad Al Abdullah', 'ar' => 'سعد العبدالله'],
                    ['en' => 'Naeem', 'ar' => 'النعيم'],
                ],
            ],
            [
                'name' => ['en' => 'Mubarak Al-Kabeer', 'ar' => 'مبارك الكبير'],
                'slug' => 'mubarak-al-kabeer',
                'cities' => [
                    ['en' => 'Sabah Al-Salem', 'ar' => 'صباح السالم'],
                    ['en' => 'Al-Qurain', 'ar' => 'القرين'],
                    ['en' => 'Al-Qusur', 'ar' => 'القصور'],
                ],
            ],
        ];

        foreach ($govs as $g) {
            $state = State::updateOrCreate(['slug' => $g['slug']], [
                'name' => $g['name'],
                'slug' => $g['slug'],
                'is_active' => true,
            ]);

            foreach ($g['cities'] as $cityName) {
                $city = City::updateOrCreate([
                    'slug' => Str::slug($cityName['en']),
                ], [
                    'state_id' => $state->id,
                    'name' => $cityName,
                    'slug' => Str::slug($cityName['en']),
                    'is_active' => true,
                ]);

                // Seed Blocks 1..6 for demo
                for ($i = 1; $i <= 6; $i++) {
                    Block::updateOrCreate([
                        'city_id' => $city->id,
                        'code' => (string) $i,
                    ], [
                        'city_id' => $city->id,
                        'code' => (string) $i,
                        'name' => ['en' => "Block $i", 'ar' => "قطعة $i"],
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
