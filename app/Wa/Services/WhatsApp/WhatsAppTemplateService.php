<?php

namespace App\Wa\Services\WhatsApp;

use App\Wa\Models\WhatsApp\WaNumber;
use App\Wa\Services\WhatsApp\Tenant\TenantWhatsAppService;

class WhatsAppTemplateService
{
    public function __construct(
        protected TenantWhatsAppService $tenantWhatsAppService,
    ) {}

    /**
     * Fetch templates from Meta for a given number.
     *
     * @return array{definitions: array<string,array>, options: array<string,array>}
     */
    public function loadTemplatesForNumber(WaNumber $number, string $status = 'APPROVED'): array
    {
        $templates = $this->tenantWhatsAppService
            ->forNumber($number)
            ->listTemplates($status);

        $definitions = [];
        $options = [];

        foreach ($templates as $tpl) {
            $name = $tpl['name'] ?? null;
            $lang = $tpl['language'] ?? null;

            if (! $name || ! $lang) {
                continue;
            }

            $key = $this->makeKey($name, $lang);

            $definitions[$key] = $tpl;

            // small helper for UI: label + preview
            $bodyText = collect($tpl['components'] ?? [])
                ->firstWhere('type', 'BODY')['text'] ?? '';

            $options[$key] = [
                'key' => $key,
                'name' => $name,
                'lang' => $lang,
                'body' => $bodyText,
                'raw' => $tpl,
            ];
        }

        return [
            'definitions' => $definitions,
            'options' => $options,
        ];
    }

    public function makeKey(string $name, string $language): string
    {
        return $name.'|'.$language;
    }

    /**
     * Build the "variables" array used in your Filament form.
     * Same logic as your buildVariableDefaultsForTemplate().
     */
    public function buildVariableDefaults(array $template): array
    {
        $components = $template['components'] ?? [];
        $indicesByContext = [];

        $scan = function (string $text, string $context) use (&$indicesByContext): void {
            if (preg_match_all('/\{\{(\d+)\}\}/', $text, $matches)) {
                foreach ($matches[1] as $idx) {
                    $i = (int) $idx;
                    $indicesByContext[$i] ??= ['contexts' => []];
                    $indicesByContext[$i]['contexts'][] = $context;
                }
            }
        };

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');

            if (in_array($type, ['HEADER', 'BODY'], true) && isset($component['text'])) {
                $scan($component['text'], strtolower($type));
            }

            if ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $btnIndex => $button) {
                    // URL button
                    if (($button['type'] ?? '') === 'URL' && isset($button['url'])) {
                        $label = $button['text'] ?? ('Button '.($btnIndex + 1));
                        $scan($button['url'], "button URL ({$label})");
                    }
                }
            }

            // (optional) CAROUSEL BODY scanning:
            if ($type === 'CAROUSEL') {
                foreach ($component['cards'] ?? [] as $cardIndex => $card) {
                    foreach ($card['components'] ?? [] as $cardComponent) {
                        $cardType = strtoupper($cardComponent['type'] ?? '');
                        if ($cardType === 'BODY' && isset($cardComponent['text'])) {
                            $scan(
                                $cardComponent['text'],
                                'carousel card body #'.($cardIndex + 1)
                            );
                        }
                    }
                }
            }
        }

        if (empty($indicesByContext)) {
            return [];
        }

        ksort($indicesByContext);

        $rows = [];
        foreach ($indicesByContext as $index => $info) {
            $rows[] = [
                'index' => $index,
                'value' => '',
                'hint' => implode(' · ', array_unique($info['contexts'])),
            ];
        }

        return $rows;
    }

    /**
     * Build Meta "components" payload from template + form data.
     * This is essentially your buildComponentsForTemplatePayload(), generalized.
     */
    public function buildComponents(array $template, array $formData): array
    {
        $components = $template['components'] ?? [];
        if (empty($components)) {
            return [];
        }

        // map index => string value
        $valueMap = [];
        foreach ($formData['variables'] ?? [] as $row) {
            $idx = (int) ($row['index'] ?? 0);
            $val = trim((string) ($row['value'] ?? ''));

            if ($idx > 0 && $val !== '') {
                $valueMap[$idx] = $val;
            }
        }

        $scanIndices = static fn (string $text): array => preg_match_all('/\{\{(\d+)\}\}/', $text, $matches)
                ? array_values(array_unique(array_map('intval', $matches[1])))
                : [];

        $buildParams = static function (array $indices, array $valueMap): array {
            sort($indices);
            $params = [];

            foreach ($indices as $i) {
                $params[] = [
                    'type' => 'text',
                    'text' => $valueMap[$i] ?? '',
                ];
            }

            return $params;
        };

        $messageComponents = [];

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');

            if ($type === 'HEADER') {
                $format = strtoupper($component['format'] ?? 'TEXT');

                if ($format === 'TEXT' && isset($component['text'])) {
                    if ($indices = $scanIndices($component['text'])) {
                        $messageComponents[] = [
                            'type' => 'header',
                            'parameters' => $buildParams($indices, $valueMap),
                        ];
                    }
                } elseif ($format === 'IMAGE') {
                    // Prefer user input, fallback to example header_handle if present
                    $url = $formData['header_image_url']
                        ?? ($component['example']['header_handle'][0] ?? null);

                    if (! empty($url)) {
                        $messageComponents[] = [
                            'type' => 'header',
                            'parameters' => [[
                                'type' => 'image',
                                'image' => ['link' => $url],
                            ]],
                        ];
                    }
                } elseif ($format === 'VIDEO') {
                    $url = $formData['header_video_url'] ?? null;
                    if (! empty($url)) {
                        $messageComponents[] = [
                            'type' => 'header',
                            'parameters' => [[
                                'type' => 'video',
                                'video' => ['link' => $url],
                            ]],
                        ];
                    }
                } elseif ($format === 'DOCUMENT') {
                    $url = $formData['header_document_url'] ?? null;
                    if (! empty($url)) {
                        $doc = ['link' => $url];
                        if (! empty($formData['header_document_filename'])) {
                            $doc['filename'] = $formData['header_document_filename'];
                        }

                        $messageComponents[] = [
                            'type' => 'header',
                            'parameters' => [[
                                'type' => 'document',
                                'document' => $doc,
                            ]],
                        ];
                    }
                }
            } elseif ($type === 'BODY' && isset($component['text'])) {
                if ($indices = $scanIndices($component['text'])) {
                    $messageComponents[] = [
                        'type' => 'body',
                        'parameters' => $buildParams($indices, $valueMap),
                    ];
                }
            } elseif ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $btnIndex => $button) {
                    if (strtoupper($button['type'] ?? '') === 'URL' && isset($button['url'])) {
                        if ($indices = $scanIndices($button['url'])) {
                            $messageComponents[] = [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => (string) $btnIndex,
                                'parameters' => $buildParams($indices, $valueMap),
                            ];
                        }
                    }

                    // QUICK_REPLY / PHONE_NUMBER / SPM usually do not need params
                }
            }

            // (Basic) pass-through for CAROUSEL: if you don't need dynamic vars inside, you
            // can leave it in template and NOT add anything here – Meta uses template definition.
        }

        return $messageComponents;
    }

    /**
     * Build the final template payload to send to Meta.
     */
    public function buildTemplatePayload(array $template, array $formData): array
    {
        $payload = [
            'name' => $template['name'],
            'language' => ['code' => $template['language']],
        ];

        $components = $this->buildComponents($template, $formData);
        if (! empty($components)) {
            $payload['components'] = $components;
        }

        return $payload;
    }
}
