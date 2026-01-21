<?php

namespace Database\Seeders;

use App\Models\ReservationTerm;
use Illuminate\Database\Seeder;

class ReservationTermSeeder extends Seeder
{
    public function run(): void
    {
        // Global (no branch), active
        ReservationTerm::updateOrCreate(
            ['branch_id' => null, 'is_active' => true],
            [
                'terms_required' => true,
                'label_en' => 'I agree to the reservation terms',
                'label_ar' => 'أوافق على شروط الحجز',
                'text_en' => "• We hold your table for 15 minutes.\n• Please notify us if you’re running late.\n• Cancel at least 2 hours ahead.\n• Groups (6+) may require a deposit.",
                'text_ar' => "• نحتفظ بطاولتك لمدة 15 دقيقة.\n• يُرجى إخطارنا في حال التأخير.\n• الإلغاء قبل ساعتين على الأقل.\n• المجموعات (6+) قد تتطلب عربونًا.",
            ]
        );
    }
}
