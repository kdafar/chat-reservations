<?php

namespace Database\Seeders;

use App\Models\MessageText;
use Illuminate\Database\Seeder;

class MessageTextSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = config('messages.defaults');

        foreach ($defaults as $key => $byLocale) {
            foreach (['en', 'ar'] as $loc) {
                $val = is_array($byLocale) ? ($byLocale[$loc] ?? null) : $byLocale;
                if (! $val) {
                    continue;
                }

                MessageText::updateOrCreate(
                    ['key' => $key, 'locale' => $loc],
                    ['value' => $val]
                );
            }
        }
    }
}
