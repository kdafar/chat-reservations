<?php

namespace App\Wa\Http\Controllers\Api;

use App\Wa\Http\Controllers\Controller;
use App\Wa\Services\WhatsAppBot; //  use the Bot, not the Service
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class WhatsappRelayController extends Controller
{
    public function send(Request $request, WhatsAppBot $wa): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'type' => ['required', Rule::in(['text', 'template', 'image'])],
            'locale' => ['sometimes', 'string', Rule::in(['en', 'ar'])],

            // text
            'text' => ['required_if:type,text', 'string', 'max:1000'],

            // template (generic passthrough)
            'template.name' => ['required_if:type,template', 'string', 'max:128'],
            'template.language' => ['required_if:type,template', 'string', 'max:10'],
            'template.components' => ['required_if:type,template', 'array'],
            'fallback_text' => ['nullable', 'string', 'max:1000'],

            // image: provide either id or link
            'image' => ['nullable', 'array', 'prohibited_unless:type,image'],
            'image.link' => ['nullable', 'url', 'max:2048'],
            'image.id' => ['nullable', 'string', 'max:256'],

            // media-template body vars (for our hospital reminder flow)
            'patient_name' => ['required_if:type,image', 'string', 'max:120'],
            'details_text' => ['nullable', 'string', 'max:1000'],
            'medication' => ['nullable', 'string', 'max:200'],
            'time' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:1000'],

            'meta' => ['sometimes', 'array'],
        ]);

        // image: require either link or id
        if (($data['type'] ?? null) === 'image') {
            $link = data_get($data, 'image.link');
            $id = data_get($data, 'image.id');
            if (empty($link) && empty($id)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Either image.link or image.id is required.',
                    'errors' => [
                        'image.link' => ['Required when image.id is missing.'],
                        'image.id' => ['Required when image.link is missing.'],
                    ],
                ], 422);
            }
        }

        try {
            if ($data['type'] === 'text') {
                // Plain text (Bot should decide about 24h policy)
                $text = $this->waSanitizeParam($data['text'] ?? '');
                $wa->sendText($data['to'], $text);

            } elseif ($data['type'] === 'template') {
                //  sanitize every TEXT parameter inside template.components
                $tplName = (string) data_get($data, 'template.name');
                $tplLang = (string) data_get($data, 'template.language', 'en'); // must match approved code exactly
                $components = (array) data_get($data, 'template.components', []);
                $fallback = $this->waSanitizeParam($data['fallback_text'] ?? '');

                $components = $this->sanitizeTemplateComponents($components, $tplName);

                $wa->sendTemplateOrText(
                    $data['to'],
                    $tplName,
                    $tplLang,
                    $components,
                    $fallback
                );

            } else {
                // type === 'image' — send your approved "hospital_reminder_image_v1_{en|ar}" template

                $to = $data['to'];
                $locale = $data['locale'] ?? 'en'; // exact codes: "en" or "ar"
                $patient = $this->waSanitizeParam($data['patient_name'] ?? '');
                $media = data_get($data, 'image.id') ?: data_get($data, 'image.link');

                // Accept caller-supplied details_text only if it's single-line and not starting with labels.
                $rawDetails = $data['details_text'] ?? null;
                if ($rawDetails !== null) {
                    $hasBadWhitespace = preg_match('/[\r\n\t\x{2028}\x{2029}]| {5,}/u', $rawDetails) === 1;
                    $startsWithLabel = preg_match('/^\s*(Hospital\s+reminder|تذكير\s+المستشفى)\s*:\s*/ui', $rawDetails) === 1;
                    if ($hasBadWhitespace || $startsWithLabel) {
                        $rawDetails = null; // force rebuild
                    }
                }

                if ($rawDetails === null) {
                    $med = trim((string) ($data['medication'] ?? ''));
                    $time = trim((string) ($data['time'] ?? ''));
                    $instr = trim((string) ($data['instructions'] ?? ''));

                    if ($med === '' || $time === '') {
                        return response()->json([
                            'ok' => false,
                            'error' => 'Provide either details_text, or medication + time (instructions optional).',
                        ], 422);
                    }

                    // Build single-line {{2}} without labels (template already has them).
                    $param2 = ($locale === 'ar')
                        ? "{$med} {$time}"
                        : "{$med} at {$time}";

                    if ($instr !== '') {
                        $param2 .= " • {$instr}";
                    }
                } else {
                    $param2 = $rawDetails;
                }

                // Final sanitize (kills hidden Unicode separators/spaces and clamps multiples)
                $param2 = $this->waSanitizeParam($param2);

                // Build components EXACTLY as approved template (HEADER: IMAGE + BODY: 2 params)
                $headerParams = preg_match('~^https?://~', (string) $media)
                    ? [['type' => 'image', 'image' => ['link' => $media]]]
                    : [['type' => 'image', 'image' => ['id' => $media]]];

                $templateName = $locale === 'ar'
                    ? 'hospital_reminder_image_v1_ar'
                    : 'hospital_reminder_image_v1_en';

                $components = [
                    ['type' => 'header', 'parameters' => $headerParams],
                    ['type' => 'body', 'parameters' => [
                        ['type' => 'text', 'text' => $patient], // {{1}}
                        ['type' => 'text', 'text' => $param2],  // {{2}}
                    ]],
                ];

                // Optional (used only inside 24h when template missing/paused):
                $fallback = $param2;

                $wa->sendTemplateOrText(
                    $to,
                    $templateName,
                    $locale,   // must be exactly "en" or "ar"
                    $components,
                    $fallback
                );
            }

            Log::info('WA relay ok', [
                'to' => $data['to'],
                'type' => $data['type'],
                'meta' => $data['meta'] ?? null,
            ]);

            return response()->json(['ok' => true]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body = optional($e->response)->json() ?? ['message' => $e->getMessage()];
            Log::warning('WA relay failed (API)', ['err' => $body, 'to' => $data['to'] ?? null]);

            return response()->json(['ok' => false, 'error' => $body], 422);

        } catch (\Throwable $e) {
            Log::error('WA relay failed', ['err' => $e->getMessage()]);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sanitize every TEXT parameter in template.components.
     * - removes newlines/tabs/control chars
     * - collapses exotic spaces
     * - optionally strips duplicate label prefixes ("Hospital reminder:" / "تذكير المستشفى:")
     * - for our hospital template only, removes a leading "<patient> — " from {{2}}
     */
    private function sanitizeTemplateComponents(array $components, ?string $tplName = null): array
    {
        // First pass: sanitize all text params
        foreach ($components as &$comp) {
            if (! isset($comp['parameters']) || ! is_array($comp['parameters'])) {
                continue;
            }
            foreach ($comp['parameters'] as &$p) {
                if (($p['type'] ?? null) === 'text') {
                    $txt = (string) ($p['text'] ?? '');
                    $txt = $this->waSanitizeParam($txt);

                    // Strip duplicate label prefixes for any hospital reminder template
                    if (is_string($tplName) && str_starts_with($tplName, 'hospital_reminder_image_v1_')) {
                        $txt = preg_replace('/^\s*(Hospital\s+reminder|تذكير\s+المستشفى)\s*:\s*/ui', '', $txt) ?? $txt;
                        $txt = $this->waSanitizeParam($txt);
                    }

                    $p['text'] = $txt;
                }
            }
            unset($p);
        }
        unset($comp);

        // Second pass (hospital template only): try to remove leading "<patient> — " from the second body text param
        if (is_string($tplName) && str_starts_with($tplName, 'hospital_reminder_image_v1_')) {
            $patientText = null;

            // find first BODY component + first text param (assumed {{1}})
            foreach ($components as $comp) {
                if (($comp['type'] ?? '') === 'body' && isset($comp['parameters']) && is_array($comp['parameters'])) {
                    foreach ($comp['parameters'] as $p) {
                        if (($p['type'] ?? null) === 'text') {
                            $patientText = $p['text'] ?? null; // first text in BODY
                            break 2;
                        }
                    }
                }
            }

            if ($patientText !== null && $patientText !== '') {
                foreach ($components as &$comp) {
                    if (($comp['type'] ?? '') !== 'body' || ! isset($comp['parameters']) || ! is_array($comp['parameters'])) {
                        continue;
                    }

                    $seenFirstText = false;
                    foreach ($comp['parameters'] as &$p) {
                        if (($p['type'] ?? null) !== 'text') {
                            continue;
                        }

                        if (! $seenFirstText) {
                            // this is {{1}} (patient) – leave it
                            $seenFirstText = true;

                            continue;
                        }

                        // this is {{2}} or later – strip a leading "<patient> — " or "<patient> - "
                        $pattern = '/^\s*'.preg_quote($patientText, '/').'\s*[—\-]\s*/u';
                        $p['text'] = preg_replace($pattern, '', $p['text'] ?? '') ?? ($p['text'] ?? '');
                        $p['text'] = $this->waSanitizeParam($p['text']);
                    }
                    unset($p);
                }
                unset($comp);
            }
        }

        return $components;
    }

    /**
     * WhatsApp param sanitizer:
     * - remove control chars (ASCII + \u2028, \u2029)
     * - replace CR/LF/TAB with a single space
     * - collapse all space types (regular, NBSP, quads, zero-width, etc.) to single spaces
     * - clamp any >4 consecutive spaces to 4
     * - trim; limit to $limit chars
     */
    private function waSanitizeParam(?string $s, int $limit = 1000): string
    {
        $s = (string) $s;

        // Remove control chars incl. Unicode line/para separators
        $s = preg_replace('/[\x{0000}-\x{001F}\x{007F}\x{2028}\x{2029}]+/u', ' ', $s);

        // Replace classic newlines/tabs
        $s = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $s);

        // Collapse all kinds of spaces (NBSP, en/em/quads, zero-width, etc.)
        $s = preg_replace('/[ \x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]+/u', ' ', $s);

        // Guard against >4 spaces (Meta rule)
        $s = preg_replace('/ {5,}/', '    ', $s);

        return mb_substr(trim($s), 0, $limit);
    }
}
