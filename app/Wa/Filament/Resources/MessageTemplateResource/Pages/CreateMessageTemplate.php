<?php

namespace App\Wa\Filament\Resources\MessageTemplateResource\Pages;

use App\Wa\Filament\Resources\MessageTemplateResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateMessageTemplate extends CreateRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1. Handle File Upload (Header Sample)
        $data['header_sample_path'] = $data['header_sample_path'] ?? null;
        $data['header_sample_media_id'] = MessageTemplateResource::extractCuratorMediaId($data['header_sample_media_id'] ?? null);
        $data['campaign_media_id'] = MessageTemplateResource::extractCuratorMediaId($data['campaign_media_id'] ?? null);

        // Validation: Verify Mime Type matches Header Type
        if (! empty($data['header_sample_path'])) {
            $disk = config('curator.disk', 'public');
            $path = ltrim((string) $data['header_sample_path'], '/');

            if (Storage::disk($disk)->exists($path)) {
                $mime = Storage::disk($disk)->mimeType($path) ?: '';
                $type = $data['header_type'] ?? 'NONE';

                $invalid = match ($type) {
                    'IMAGE' => ! str_starts_with($mime, 'image/'),
                    'VIDEO' => ! str_starts_with($mime, 'video/'),
                    'DOCUMENT' => $mime !== 'application/pdf',
                    default => false,
                };

                if ($invalid) {
                    Notification::make()
                        ->danger()
                        ->title('Invalid Media Type')
                        ->body("The selected file ($mime) does not match Header Type ($type).")
                        ->send();

                    $this->halt();
                }
            }
        }

        // 2. Build Components JSON
        $components = [];

        // --- HEADER ---
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

        // --- BODY ---
        // FIX: Read from 'body', not 'body_text'
        $bodyText = (string) ($data['body'] ?? '');

        $body = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];

        // Map Body Examples
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

        // --- FOOTER ---
        $footerText = trim((string) ($data['footer_text'] ?? ''));
        if ($footerText !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $footerText,
            ];
        }

        // --- BUTTONS ---
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
        // Ensure 'body' is preserved
        $data['body'] = $bodyText;
        $data['body_preview'] = $bodyText;
        $data['local_status'] = 'draft';

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
