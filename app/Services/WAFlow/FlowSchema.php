<?php

namespace App\Services\WAFlow;

final class FlowSchema
{
    /**
     * Ensure dropdown options contain only {id, title, image?} and are strings.
     * WhatsApp Flow will reject extra keys like "enabled".
     */
    public function dropdownOptions(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $id = isset($it['id']) ? (string) $it['id'] : '';
            $title = isset($it['title']) ? (string) $it['title'] : '';
            if ($id === '' || $title === '') {
                continue;
            }

            $row = ['id' => $id, 'title' => $title];

            if (isset($it['image']) && is_string($it['image']) && $it['image'] !== '') {
                // must be RAW base64 (no data: prefix) — FlowAssets already returns that
                $row['image'] = $it['image'];
            }
            $out[] = $row;
        }

        // cap at 10 per Meta guidelines
        return array_slice($out, 0, 10);
    }
}
