<?php

namespace App\Services;

use App\Models\MessageText;
use Illuminate\Support\Facades\Cache;

class MessageCatalog
{
    public function get(string $key, string $locale = 'en', array $vars = []): string
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';

        $val = $this->lookup($key, $locale)
            ?? $this->lookup($key, 'en')
            ?? $this->configDefault($key, $locale)
            ?? $this->configDefault($key, 'en')
            ?? $key; // last-resort shows key

        if (! empty($vars)) {
            $val = $this->interpolate($val, $vars);
        }

        return $val;
    }

    protected function lookup(string $key, string $locale): ?string
    {
        $all = Cache::remember('message_texts_map', 60, function () {
            return MessageText::query()
                ->get(['key', 'locale', 'value'])
                ->groupBy('key')
                ->map(fn ($rows) => $rows->keyBy('locale')->map->value->all())
                ->all();
        });

        return $all[$key][$locale] ?? null;
    }

    protected function configDefault(string $key, string $locale): ?string
    {
        $defaults = config('messages.defaults');
        if (! isset($defaults[$key])) {
            return null;
        }
        $entry = $defaults[$key];

        return is_array($entry) ? ($entry[$locale] ?? null) : (string) $entry;
    }

    protected function interpolate(string $text, array $vars): string
    {
        // Replaces {var} tokens
        $replace = [];
        foreach ($vars as $k => $v) {
            $replace['{'.$k.'}'] = (string) $v;
        }

        return strtr($text, $replace);
    }

    public function flushCache(): void
    {
        Cache::forget('message_texts_map');
    }
}
