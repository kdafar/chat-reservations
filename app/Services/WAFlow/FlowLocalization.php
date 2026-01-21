<?php

namespace App\Services\WAFlow;

use Carbon\Carbon;

class FlowLocalization
{
    public function tr(string $key, string $locale): string
    {
        $map = [
            'pick_again' => [
                'en' => 'Please select branch, party size, date & time again.',
                'ar' => 'يرجى اختيار الفرع وعدد الأشخاص والتاريخ والوقت مرة أخرى.',
            ],
            'slot_taken' => [
                'en' => 'Oops, that time was just taken. Pick another slot.',
                'ar' => 'للأسف هذا الوقت لم يعد متاحًا. اختر وقتًا آخر.',
            ],
            'hold_expired' => [
                'en' => 'Your hold expired. Please select a time again.',
                'ar' => 'انتهى حجزك المؤقت. يرجى اختيار الوقت مرة أخرى.',
            ],
        ];

        return $map[$key][$locale] ?? $map[$key]['en'] ?? $key;
    }

    public function humanTime(string $hhmm, string $locale): string
    {
        try {
            $t = Carbon::createFromFormat('H:i', $hhmm, config('app.timezone', 'Asia/Kuwait'));

            return $locale === 'ar' ? $t->format('H:i') : $t->format('g:i A');
        } catch (\Throwable) {
            return $hhmm;
        }
    }
}
