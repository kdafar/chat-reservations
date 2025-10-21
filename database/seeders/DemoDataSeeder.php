<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Cuisine;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Partner;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Services
        $food = Service::updateOrCreate(
            ['slug' => 'food'],
            ['name' => ['en' => 'Food', 'ar' => 'مطاعم'], 'icon' => '🍔', 'is_active' => true]
        );

        // ---- Cuisines
        $cuisines = collect([
            ['slug' => 'burgers', 'name' => ['en' => 'Burgers', 'ar' => 'برجر']],
            ['slug' => 'shawarma', 'name' => ['en' => 'Shawarma', 'ar' => 'شاورما']],
            ['slug' => 'sushi', 'name' => ['en' => 'Sushi', 'ar' => 'سوشي']],
            ['slug' => 'pizza', 'name' => ['en' => 'Pizza', 'ar' => 'بيتزا']],
        ])->mapWithKeys(function ($c) {
            $model = Cuisine::updateOrCreate(['slug' => $c['slug']], $c + ['is_active' => true]);

            return [$c['slug'] => $model->id];
        });

        // ---- Try to grab Kuwait cities if table exists (optional)
        $kuwaitCityId = null;
        $hawalliId = null;
        if (Schema::hasTable('cities')) {
            $kuwaitCityId = DB::table('cities')->where('name', 'Kuwait City')->value('id')
                ?? DB::table('cities')->where('name', 'مدينة الكويت')->value('id');
            $hawalliId = DB::table('cities')->where('name', 'Hawalli')->value('id')
                ?? DB::table('cities')->where('name', 'حولي')->value('id');
        }

        // ---- Partners
        $partners = [
            [
                'slug' => 'burger-stars',
                'name' => ['en' => 'Burger Stars', 'ar' => 'برجر ستارز'],
                'logo_path' => null,
                'branches' => [
                    ['name' => ['en' => 'City Branch', 'ar' => 'فرع المدينة'], 'city_id' => $kuwaitCityId, 'lat' => 29.3759, 'lng' => 47.9774, 'cuisines' => ['burgers', 'pizza']],
                    ['name' => ['en' => 'Hawalli Branch', 'ar' => 'فرع حولي'], 'city_id' => $hawalliId, 'lat' => 29.3375, 'lng' => 48.0286, 'cuisines' => ['burgers']],
                ],
            ],
            [
                'slug' => 'shawarma-house',
                'name' => ['en' => 'Shawarma House', 'ar' => 'شاورما هاوس'],
                'logo_path' => null,
                'branches' => [
                    ['name' => ['en' => 'City Branch', 'ar' => 'فرع المدينة'], 'city_id' => $kuwaitCityId, 'lat' => 29.3720, 'lng' => 47.9820, 'cuisines' => ['shawarma']],
                ],
            ],
            [
                'slug' => 'sushi-al-kuwait',
                'name' => ['en' => 'Sushi Al-Kuwait', 'ar' => 'سوشي الكويت'],
                'logo_path' => null,
                'branches' => [
                    ['name' => ['en' => 'Seaside', 'ar' => 'الواجهة البحرية'], 'city_id' => $kuwaitCityId, 'lat' => 29.3790, 'lng' => 47.9900, 'cuisines' => ['sushi']],
                ],
            ],
        ];

        foreach ($partners as $p) {
            $partner = Partner::updateOrCreate(['slug' => $p['slug']], [
                'name' => $p['name'],
                'logo_path' => $p['logo_path'],
                'is_active' => true,
            ]);

            foreach ($p['branches'] as $b) {
                $branch = Branch::updateOrCreate([
                    'partner_id' => $partner->id,
                    'name->en' => $b['name']['en'], // to avoid duplicates
                ], [
                    'partner_id' => $partner->id,
                    'name' => $b['name'],
                    'city_id' => $b['city_id'],
                    'latitude' => $b['lat'],
                    'longitude' => $b['lng'],
                    'is_available' => true,
                ]);

                // Service attach
                $branch->services()->syncWithoutDetaching([$food->id]);

                // Cuisine attach
                $branch->cuisines()->syncWithoutDetaching(
                    collect($b['cuisines'])->map(fn ($slug) => $cuisines[$slug] ?? null)->filter()->all()
                );

                // --- Menu + sections + items (per branch)
                $menu = Menu::updateOrCreate([
                    'branch_id' => $branch->id,
                    'name->en' => 'Main Menu',
                ], [
                    'branch_id' => $branch->id,
                    'name' => ['en' => 'Main Menu', 'ar' => 'القائمة الرئيسية'],
                    'description' => ['en' => 'Sample menu for demo', 'ar' => 'قائمة تجريبية'],
                    'is_active' => true,
                ]);

                // Sections depend on cuisines present
                $sectionNames = match (true) {
                    $branch->cuisines->pluck('slug')->contains('burgers') => [
                        ['en' => 'Burgers', 'ar' => 'برجر'],
                        ['en' => 'Combos', 'ar' => 'وجبات'],
                        ['en' => 'Sides', 'ar' => 'مقبلات'],
                    ],
                    $branch->cuisines->pluck('slug')->contains('shawarma') => [
                        ['en' => 'Shawarma', 'ar' => 'شاورما'],
                        ['en' => 'Plates', 'ar' => 'أطباق'],
                    ],
                    $branch->cuisines->pluck('slug')->contains('sushi') => [
                        ['en' => 'Sushi Rolls', 'ar' => 'رولات سوشي'],
                        ['en' => 'Nigiri', 'ar' => 'نِجيري'],
                    ],
                    default => [['en' => 'Items', 'ar' => 'أصناف']],
                };

                $sections = [];
                foreach ($sectionNames as $i => $nm) {
                    $sections[$i] = MenuSection::updateOrCreate([
                        'menu_id' => $menu->id,
                        'name->en' => $nm['en'],
                    ], [
                        'menu_id' => $menu->id,
                        'name' => $nm,
                        'sort_order' => $i,
                    ]);
                }

                // Items (small fixed set for demo)
                $addItem = function ($section, $enName, $arName, $price, $descEn = null, $descAr = null) use ($branch) {
                    return MenuItem::updateOrCreate([
                        'menu_section_id' => $section->id,
                        'name->en' => $enName,
                    ], [
                        'menu_section_id' => $section->id,
                        'branch_id' => $branch->id,
                        'name' => ['en' => $enName, 'ar' => $arName],
                        'description' => ['en' => $descEn, 'ar' => $descAr],
                        'image_path' => null,
                        'sku' => Str::upper(Str::slug($enName)).'-'.Str::random(4),
                        'price' => $price,
                        'is_available' => true,
                    ]);
                };

                if ($branch->cuisines->pluck('slug')->contains('burgers')) {
                    $i1 = $addItem($sections[0], 'Classic Beef Burger', 'برجر لحم كلاسيك', 2.750, '180g beef, cheddar, sauce', '180غ لحم، جبنة، صوص');
                    $i2 = $addItem($sections[0], 'Chicken Burger', 'برجر دجاج', 2.250);
                    $i3 = $addItem($sections[1], 'Double Combo', 'وجبة دبل', 3.900, 'Burger + fries + drink', 'برجر + بطاطس + مشروب');
                    $addItem($sections[2], 'Fries', 'بطاطس', 0.650);
                    $addItem($sections[2], 'Onion Rings', 'حلقات بصل', 0.950);

                    // Modifiers for burgers
                    $grp = ModifierGroup::updateOrCreate([
                        'branch_id' => $branch->id,
                        'name->en' => 'Sauce',
                    ], [
                        'branch_id' => $branch->id,
                        'name' => ['en' => 'Sauce', 'ar' => 'صوص'],
                        'is_required' => false,
                        'min_choices' => 0,
                        'max_choices' => 2,
                    ]);

                    foreach ([
                        ['en' => 'Garlic', 'ar' => 'ثوم', 'delta' => 0.000],
                        ['en' => 'Spicy', 'ar' => 'حار',   'delta' => 0.100],
                        ['en' => 'BBQ', 'ar' => 'باربكيو', 'delta' => 0.150],
                    ] as $opt) {
                        ModifierOption::updateOrCreate([
                            'modifier_group_id' => $grp->id,
                            'name->en' => $opt['en'],
                        ], [
                            'modifier_group_id' => $grp->id,
                            'name' => ['en' => $opt['en'], 'ar' => $opt['ar']],
                            'price_delta' => $opt['delta'],
                            'is_default' => false,
                        ]);
                    }

                    // Attach sauce group to two items
                    DB::table('item_modifier_option')->updateOrInsert([
                        'menu_item_id' => $i1->id, 'modifier_group_id' => $grp->id,
                    ], ['menu_item_id' => $i1->id, 'modifier_group_id' => $grp->id]);
                    DB::table('item_modifier_option')->updateOrInsert([
                        'menu_item_id' => $i2->id, 'modifier_group_id' => $grp->id,
                    ], ['menu_item_id' => $i2->id, 'modifier_group_id' => $grp->id]);
                }

                if ($branch->cuisines->pluck('slug')->contains('shawarma')) {
                    $addItem($sections[0], 'Chicken Shawarma Sandwich', 'سندويتش شاورما دجاج', 1.250);
                    $addItem($sections[0], 'Beef Shawarma Sandwich', 'سندويتش شاورما لحم', 1.450);
                    $addItem($sections[1], 'Shawarma Plate', 'طبق شاورما', 2.900, 'With sides', 'مع المقبلات');
                }

                if ($branch->cuisines->pluck('slug')->contains('sushi')) {
                    $addItem($sections[0], 'California Roll (8 pcs)', 'كاليفورنيا رول (٨ قطع)', 3.200);
                    $addItem($sections[0], 'Spicy Tuna Roll (8 pcs)', 'سبايسي تونة رول (٨ قطع)', 3.600);
                    $addItem($sections[1], 'Salmon Nigiri (2 pcs)', 'سالمون نجيري (٢ قطعة)', 1.400);
                }

                // Delivery areas (optional; if cities table exists)
                if (Schema::hasTable('delivery_areas')) {
                    foreach (array_filter([$kuwaitCityId, $hawalliId]) as $cityId) {
                        DB::table('delivery_areas')->updateOrInsert([
                            'branch_id' => $branch->id, 'city_id' => $cityId, 'block_id' => null,
                        ], [
                            'branch_id' => $branch->id,
                            'city_id' => $cityId,
                            'block_id' => null,
                            'delivery_fee' => 0.000,
                            'min_order_value' => 0.000,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}
