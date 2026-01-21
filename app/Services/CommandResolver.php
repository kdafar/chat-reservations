<?php

// app/Services/CommandResolver.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CommandResolver
{
    /**
     * Resolve a free-text message into an action.
     * Returns: ['action' => 'start|reset|menu|help|jump', 'params' => array] or null.
     */
    public function resolve(?string $text, string $lang = 'en'): ?array
    {
        if (! $text) {
            return null;
        }
        $norm = $this->norm($text);

        // 1) Try admin-defined commands (if model/table exists)
        $records = $this->loadAdminCommands();

        foreach ($records as $cmd) {
            $locale = $cmd['locale'] ?? $cmd['lang'] ?? null;         // tolerate either column name
            $action = $cmd['action'] ?? 'jump';
            $params = (array) ($cmd['params'] ?? []);
            $active = (bool) ($cmd['is_active'] ?? true);
            if (! $active) {
                continue;
            }

            $triggers = $cmd['trigger'] ?? $cmd['phrase'] ?? $cmd['triggers'] ?? null;
            if (is_string($triggers)) {
                // allow comma/pipe separation
                $triggers = preg_split('/[,\|]/', $triggers) ?: [];
            }
            if (! is_array($triggers)) {
                continue;
            }

            foreach ($triggers as $t) {
                if ($this->norm($t) === $norm && (! $locale || $this->norm($locale) === $this->norm($lang))) {
                    return ['action' => $action, 'params' => $params];
                }
            }
        }

        // 2) Fallback defaults (EN/AR)
        $defaults = [
            'en' => [
                'hi' => 'start', 'hello' => 'start', 'start' => 'start',
                'reset' => 'reset', 'menu' => 'menu', 'help' => 'help',
            ],
            'ar' => [
                'مرحبا' => 'start', 'ابدأ' => 'start', 'بدء' => 'start',
                'إعادة' => 'reset', 'قائمة' => 'menu', 'مساعدة' => 'help',
            ],
        ];
        $map = $defaults[$lang] ?? $defaults['en'];
        if (isset($map[$norm])) {
            return ['action' => $map[$norm], 'params' => []];
        }

        return null;
    }

    /** Cached loader that avoids hard-dependence if the model/table doesn't exist. */
    protected function loadAdminCommands(): array
    {
        return Cache::remember('wa_cmd_records', 30, function () {
            try {
                if (! class_exists(\App\Models\WACommand::class)) {
                    return [];
                }

                // Pick common columns; cast to array for flexible schema
                return \App\Models\WACommand::query()
                    ->select(['id', 'locale', 'lang', 'trigger', 'phrase', 'triggers', 'action', 'params', 'is_active'])
                    ->get()
                    ->map(function ($m) {
                        $row = $m->toArray();
                        // ensure params is an array
                        if (isset($row['params']) && is_string($row['params'])) {
                            $decoded = json_decode($row['params'], true);
                            if (is_array($decoded)) {
                                $row['params'] = $decoded;
                            }
                        }

                        return $row;
                    })
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    protected function norm(?string $t): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', (string) $t)));
    }
}
