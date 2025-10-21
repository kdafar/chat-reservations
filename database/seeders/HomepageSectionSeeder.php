<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        if (HomepageSection::query()->exists()) {
            return;
        }

        HomepageSection::create([
            'title' => ['en' => 'Discover great food near you', 'ar' => 'اكتشف أفضل المطاعم بالقرب منك'],
            'subtitle' => ['en' => 'Order in minutes', 'ar' => 'اطلب خلال دقائق'],
            'hero_image_path' => null,
            'show_featured_cuisines' => true,
            'show_featured_partners' => true,
            'show_trending_items' => true,
        ]);
    }
}
