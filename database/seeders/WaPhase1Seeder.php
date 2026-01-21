<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\WACommand;
use App\Models\WAMessage;
use Illuminate\Database\Seeder;

class WaPhase1Seeder extends Seeder
{
    public function run(): void
    {
        // Commands (EN)
        $commands = [
            ['keyword' => 'hi', 'language' => 'en', 'action' => 'reset', 'priority' => 10],
            ['keyword' => 'hello', 'language' => 'en', 'action' => 'reset', 'priority' => 20],
            ['keyword' => 'start', 'language' => 'en', 'action' => 'reset', 'priority' => 30],
            ['keyword' => 'reset', 'language' => 'en', 'action' => 'reset', 'priority' => 40],
            ['keyword' => 'menu', 'language' => 'en', 'action' => 'menu', 'priority' => 50],
            ['keyword' => 'help', 'language' => 'en', 'action' => 'menu', 'priority' => 60],
            // Arabic basics
            ['keyword' => 'مرحبا', 'language' => 'ar', 'action' => 'reset', 'priority' => 10],
            ['keyword' => 'ابدأ', 'language' => 'ar', 'action' => 'reset', 'priority' => 20],
            ['keyword' => 'إعادة', 'language' => 'ar', 'action' => 'reset', 'priority' => 30],
            ['keyword' => 'قائمة', 'language' => 'ar', 'action' => 'menu', 'priority' => 40],
            ['keyword' => 'مساعدة', 'language' => 'ar', 'action' => 'menu', 'priority' => 50],
        ];

        foreach ($commands as $c) {
            WACommand::updateOrCreate(
                ['keyword' => $c['keyword'], 'language' => $c['language']],
                ['action' => $c['action'], 'priority' => $c['priority'], 'enabled' => true]
            );
        }

        // Message catalog (minimal to start)
        $msgs = [
            ['key' => 'booking.active_found', 'language' => 'en', 'text' => 'You already have a booking on {date} at {time} in {branch}. Change it or book new?'],
            ['key' => 'booking.active_found', 'language' => 'ar', 'text' => 'لديك حجز بتاريخ {date} الساعة {time} في {branch}. هل تريد التعديل أم إنشاء حجز جديد؟'],

            ['key' => 'booking.no_slots', 'language' => 'en', 'text' => 'No slots for that date. Please pick another date.'],
            ['key' => 'booking.no_slots', 'language' => 'ar', 'text' => 'لا توجد أوقات متاحة لهذا التاريخ. الرجاء اختيار تاريخ آخر.'],

            ['key' => 'booking.hold_taken', 'language' => 'en', 'text' => 'Oops, that time was just taken. Please pick another time.'],
            ['key' => 'booking.hold_taken', 'language' => 'ar', 'text' => 'عذراً، تم حجز هذا الوقت للتو. الرجاء اختيار وقت آخر.'],

            ['key' => 'booking.confirmed_text', 'language' => 'en', 'text' => "BOOKING CONFIRMED!\nParty: {party_size}\nDate: {date}\nTime: {time}\nBranch: {branch}\nCode: {code}\n\nThank you for choosing Barfres."],
            ['key' => 'booking.confirmed_text', 'language' => 'ar', 'text' => "تم تأكيد الحجز!\nعدد الأشخاص: {party_size}\nالتاريخ: {date}\nالوقت: {time}\nالفرع: {branch}\nالرمز: {code}\n\nشكراً لاختيارك بارفريس."],

            ['key' => 'system.template_pending', 'language' => 'en', 'text' => 'Template not available yet; sending plain confirmation instead.'],
            ['key' => 'system.template_pending', 'language' => 'ar', 'text' => 'القالب غير متاح حالياً؛ سيتم إرسال تأكيد نصي بدلاً من ذلك.'],
        ];

        foreach ($msgs as $m) {
            WAMessage::updateOrCreate(
                ['key' => $m['key'], 'language' => $m['language']],
                ['text' => $m['text'], 'enabled' => true]
            );
        }

        // Feature flags
        $flags = [
            'use_flows' => false,
            'fallback_when_template_pending' => true,
            'session_expiry_minutes' => 60,
        ];
        foreach ($flags as $k => $v) {
            SystemSetting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
    }
}
