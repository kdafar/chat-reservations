<?php

namespace App\Wa\Filament\Resources\MessageTemplateResource\Pages;

use App\Wa\Filament\Resources\MessageTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $components = $data['components'] ?? [];
        if (is_string($components)) {
            $components = json_decode($components, true) ?? [];
        }

        // ---------------- HEADER ----------------
        $header = collect($components)->firstWhere('type', 'HEADER');
        $headerFormat = $header['format'] ?? 'NONE';

        $data['header_type'] = $headerFormat ?? 'NONE';
        $data['header_text'] = (($headerFormat ?? null) === 'TEXT') ? ($header['text'] ?? '') : '';

        // Header Example
        $headerExample = $header['example']['header_text'][0] ?? null;
        $data['header_example'] = is_string($headerExample) ? $headerExample : null;

        // ---------------- BODY ----------------
        // FIX: Ensure we populate 'body' (the actual form field), not 'body_text'
        $body = collect($components)->firstWhere('type', 'BODY');
        $data['body'] = $body['text'] ?? ($data['body'] ?? '');

        // Body Examples
        $ex = $body['example']['body_text'][0] ?? [];
        if (is_array($ex)) {
            $data['body_examples'] = collect($ex)->map(fn ($v) => ['value' => $v])->values()->all();
        } else {
            $data['body_examples'] = [];
        }

        // ---------------- FOOTER ----------------
        $footer = collect($components)->firstWhere('type', 'FOOTER');
        $data['footer_text'] = $footer['text'] ?? '';

        // ---------------- BUTTONS ----------------
        $buttonsComponent = collect($components)->firstWhere('type', 'BUTTONS');
        $buttons = [];

        foreach (($buttonsComponent['buttons'] ?? []) as $btn) {
            $mapped = [
                'type' => $btn['type'] ?? null,
                'text' => $btn['text'] ?? null,
            ];

            if (($btn['type'] ?? null) === 'URL') {
                $mapped['url'] = $btn['url'] ?? '';
            }

            if (($btn['type'] ?? null) === 'PHONE_NUMBER') {
                $mapped['phone_number'] = $btn['phone_number'] ?? '';
            }

            $buttons[] = $mapped;
        }

        $data['buttons_data'] = $buttons;

        // Hydrate the FileUpload field from DB column
        $data['header_sample_path'] = $this->getRecord()->getRawOriginal('header_sample_path');
        $data['header_sample_media_id'] = $this->getRecord()->getRawOriginal('header_sample_media_id');
        $data['campaign_media_id'] = $this->getRecord()->getRawOriginal('campaign_media_id');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 1. Persist the uploaded sample path into DB column
        $data['header_sample_path'] = $data['header_sample_path'] ?? null;

        // 2. Rebuild components structure
        $components = [];

        // ---------------- HEADER ----------------
        $headerType = $data['header_type'] ?? 'NONE';

        if ($headerType === 'TEXT') {
            $headerText = (string) ($data['header_text'] ?? '');

            $headerComp = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $headerText,
            ];

            if (str_contains($headerText, '{{1}}')) {
                $sample = trim((string) ($data['header_example'] ?? ''));
                if ($sample !== '') {
                    $headerComp['example'] = [
                        'header_text' => [$sample],
                    ];
                }
            }

            $components[] = $headerComp;

        } elseif (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'], true)) {
            $components[] = [
                'type' => 'HEADER',
                'format' => $headerType,
            ];
        }

        // ---------------- BODY ----------------
        // FIX: Read from 'body', not 'body_text'
        $bodyText = (string) ($data['body'] ?? '');

        $body = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];

        // Process Body Examples
        $examplesRows = $data['body_examples'] ?? [];
        if (is_array($examplesRows) && ! empty($examplesRows)) {
            $vals = [];
            foreach ($examplesRows as $row) {
                $vals[] = (string) ($row['value'] ?? '');
            }

            $hasAny = collect($vals)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty();
            if ($hasAny) {
                $body['example'] = ['body_text' => [$vals]];
            }
        }

        $components[] = $body;

        // ---------------- FOOTER ----------------
        $footerText = trim((string) ($data['footer_text'] ?? ''));
        if ($footerText !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $footerText,
            ];
        }

        // ---------------- BUTTONS ----------------
        $buttonsData = $data['buttons_data'] ?? [];
        if (is_array($buttonsData) && ! empty($buttonsData)) {
            $buttons = [];

            foreach ($buttonsData as $btn) {
                $type = $btn['type'] ?? null;
                $text = trim((string) ($btn['text'] ?? ''));

                if (! $type || $text === '') {
                    continue;
                }

                $item = ['type' => $type, 'text' => $text];

                if ($type === 'URL') {
                    $item['url'] = (string) ($btn['url'] ?? '');
                } elseif ($type === 'PHONE_NUMBER') {
                    $item['phone_number'] = (string) ($btn['phone_number'] ?? '');
                }

                $buttons[] = $item;
            }

            if (! empty($buttons)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttons,
                ];
            }
        }

        // 3. Finalize Data
        $data['components'] = $components;
        // Ensure 'body' matches the components text (though they should be identical now)
        $data['body'] = $bodyText;
        $data['body_preview'] = $bodyText;

        $data['header_sample_media_id'] = MessageTemplateResource::extractCuratorMediaId($data['header_sample_media_id'] ?? null);
        $data['campaign_media_id'] = MessageTemplateResource::extractCuratorMediaId($data['campaign_media_id'] ?? null);

        // 4. Remove UI-only fields
        unset(
            $data['header_type'],
            $data['header_text'],
            $data['header_example'],
            $data['header_sample'],
            // $data['body'] is a DB column, DO NOT unset it
            $data['body_examples'],
            $data['footer_text'],
            $data['buttons_data']
        );

        return $data;
    }
}
