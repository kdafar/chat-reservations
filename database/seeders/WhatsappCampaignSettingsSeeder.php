<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class WhatsappCampaignSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // single JSON blob under "wa.campaigns"
        SystemSetting::updateOrCreate(
            ['key' => 'wa.campaigns'],
            ['value' => [
                'batch_size' => 200,  // recipients pulled per cycle
                'mps' => 5,    // messages scheduled per second
                'pair_gap_seconds' => 6,    // gentle spacing per user (optional usage)
                'quiet_start' => 21,   // 21:00 → 09:00 Kuwait
                'quiet_end' => 9,
                'sending_paused' => false, // Filament toggle
            ]]
        );

        SystemSetting::updateOrCreate(['key' => 'whatsapp.rate_limit.enabled'], ['value' => true]);
        SystemSetting::updateOrCreate(['key' => 'whatsapp.rate_limit.window_seconds'], ['value' => 20]);
        SystemSetting::updateOrCreate(['key' => 'whatsapp.rate_limit.limit'], ['value' => 3]);
        SystemSetting::updateOrCreate(['key' => 'whatsapp.rate_limit.cooldown_seconds'], ['value' => 30]);
        SystemSetting::updateOrCreate(['key' => 'whatsapp.rate_limit.message_en'], ['value' => 'You’re sending messages too quickly. Please try again in {seconds}s.']);
        SystemSetting::updateOrCreate(['key' => 'whatsapp.rate_limit.message_ar'], ['value' => 'تم تقييد الرسائل مؤقتًا بسبب كثرة الإرسال. الرجاء المحاولة بعد {seconds} ثانية.']);
        SystemSetting::updateOrCreate(['key' => 'whatsapp.flow.enabled'], ['value' => true]);
    }
}
