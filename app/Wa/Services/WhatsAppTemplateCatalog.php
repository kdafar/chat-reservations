<?php

namespace App\Wa\Services;

use App\Wa\Hub\Models\MessageTemplate;

class WhatsAppTemplateCatalog
{
    public function options(): array
    {
        return MessageTemplate::query()
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($item) {
                // Using Name as Key is risky if there are duplicates or special chars
                // Showing Language helps you identify if it's a specific lang missing
                return [$item->name => $item->name.' ('.strtoupper($item->language).')'];
            })
            ->toArray();
    }

    public function find(string $name): ?array
    {
        $tpl = MessageTemplate::where('name', $name)->first();

        if (! $tpl) {
            return null;
        }

        return [
            'name' => $tpl->name,
            'language' => $tpl->language ?? 'en',
            'components' => $tpl->components ?? [],
        ];
    }
}
