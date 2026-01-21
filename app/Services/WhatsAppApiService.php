<?php

namespace App\Services;

use App\Models\WhatsappSession;
use App\Models\WhatsappTrigger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Netflie\WhatsAppCloudApi\Message\OptionsList\Action as ListAction;
use Netflie\WhatsAppCloudApi\Message\Template\Component;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class WhatsAppApiService
{
    public function __construct(protected WhatsAppCloudApi $client) {}

    /* ------------------------------------------------------------------
     | Config helpers
     |-------------------------------------------------------------------*/
    private function graphToken(): string
    {
        return (string) (
            config('services.whatsapp.access_token')
            ?: env('WHATSAPP_ACCESS_TOKEN')
            ?: env('WHATSAPP_API_TOKEN')
        );
    }

    private function graphVersion(): string
    {
        return (string) config('services.whatsapp.graph_version', 'v24.0');
    }

    private function phoneId(): string
    {
        return (string) (
            config('services.whatsapp.phone_id')
            ?: config('services.whatsapp.phone_number_id')
            ?: env('WHATSAPP_PHONE_NUMBER_ID')
        );
    }

    private function endpoint(string $path): string
    {
        return "https://graph.facebook.com/{$this->graphVersion()}/{$this->phoneId()}/{$path}";
    }

    /* ------------------------------------------------------------------
     | Core HTTP sender (POST /{phone_id}/messages)
     |-------------------------------------------------------------------*/
    public function send(array $payload): array
    {
        $token = $this->graphToken();
        $phoneId = $this->phoneId();

        if ($token === '') {
            throw new \RuntimeException('WhatsApp send failed: missing access token (services.whatsapp.access_token / WHATSAPP_API_TOKEN).');
        }
        if ($phoneId === '') {
            throw new \RuntimeException('WhatsApp send failed: missing phone id (services.whatsapp.phone_id / phone_number_id).');
        }

        // Normalize "to"
        if (isset($payload['to'])) {
            $payload['to'] = $this->digitsOnly((string) $payload['to']);
        }

        $endpoint = $this->endpoint('messages');
        Log::debug('[WA] POST /messages', ['endpoint' => $endpoint, 'payload' => $payload]);

        $resp = Http::withToken($token)->asJson()->acceptJson()->post($endpoint, $payload);
        $json = $resp->json() ?? [];

        if ($resp->successful()) {
            Log::debug('[WA] send OK', ['status' => $resp->status(), 'json' => $json]);

            return $json ?: ['success' => true];
        }

        Log::error('[WA] send failed', [
            'status' => $resp->status(),
            'json' => $json,
            'body' => $resp->body(),
        ]);

        throw new \RuntimeException($resp->body() ?: 'WhatsApp send failed');
    }

    /** Alias for legacy callers – returns JSON array */
    public function sendRaw(array $payload): array
    {
        return $this->send($payload);
    }

    /* ------------------------------------------------------------------
     | Read receipts
     |-------------------------------------------------------------------*/
    public function markAsReadRaw(string $wamid): array
    {
        return $this->sendRaw([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $wamid,
        ]);
    }

    public function markAsRead(string $wamid): bool
    {
        try {
            return $this->ok($this->markAsReadRaw($wamid));
        } catch (\Throwable $e) {
            Log::warning('WA: Failed to send read receipt', [
                'wamid' => $wamid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /* ------------------------------------------------------------------
     | Plain text (Graph)
     |-------------------------------------------------------------------*/
    public function sendTextRaw(string $to, string $text, bool $previewUrl = true): array
    {
        $text = $this->normalizeText($text);

        return $this->sendRaw([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $text,
            ],
        ]);
    }

    public function sendTextMessage(string $to, string $text): bool
    {
        try {
            return $this->ok($this->sendTextRaw($to, $text, true));
        } catch (\Throwable $e) {
            Log::warning('WA: sendText exception', ['e' => $e->getMessage()]);

            return false;
        }
    }

    /* ------------------------------------------------------------------
     | Image (by link)
     |-------------------------------------------------------------------*/
    public function sendImageRaw(string $to, string $link, string $caption = ''): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => ['link' => $link],
        ];
        if ($caption !== '') {
            $payload['image']['caption'] = $this->normalizeText($caption);
        }

        return $this->sendRaw($payload);
    }

    public function sendImage(string $to, string $link, ?string $caption = null): bool
    {
        try {
            return $this->ok($this->sendImageRaw($to, $link, (string) ($caption ?? '')));
        } catch (\Throwable $e) {
            Log::warning('WA: sendImage exception', ['e' => $e->getMessage()]);

            return false;
        }
    }

    /* ------------------------------------------------------------------
     | Templates
     |-------------------------------------------------------------------*/
    public function sendTemplate(string $to, string $template, string $language = 'en_US', ?Component $components = null): void
    {
        $this->client->sendTemplate($this->digitsOnly($to), $template, $language, $components);
    }

    public function sendTemplateRaw(
        string $to,
        string $template,
        string|array $language,
        array $headerParams = [],
        array $bodyParams = [],
        array $buttonParams = [],
    ): array {
        $langBlock = is_array($language)
            ? $language
            : ['code' => (string) $language, 'policy' => 'deterministic'];

        $components = [];

        if (! empty($headerParams)) {
            $components[] = [
                'type' => 'HEADER',
                'parameters' => array_values($headerParams),
            ];
        }

        if (! empty($bodyParams)) {
            $normalizedBody = array_map(function ($v) {
                return is_array($v) ? $v : ['type' => 'text', 'text' => (string) $v];
            }, $bodyParams);

            $components[] = [
                'type' => 'BODY',
                'parameters' => array_values($normalizedBody),
            ];
        }

        foreach (array_values($buttonParams) as $i => $btn) {
            $components[] = [
                'type' => 'BUTTON',
                'sub_type' => strtoupper((string) ($btn['sub_type'] ?? 'QUICK_REPLY')),
                'index' => (string) ($btn['index'] ?? $i),
                'parameters' => array_values($btn['parameters'] ?? []),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->digitsOnly($to),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => $langBlock,
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        Log::debug('[WA Raw] payload', ['payload' => $payload]);

        return $this->sendRaw($payload);
    }

    /* ------------------------------------------------------------------
     | Template listing (Graph)
     |-------------------------------------------------------------------*/
    public function listTemplates(array $fields = ['name', 'language', 'status', 'components'], int $limit = 200): array
    {
        $token = $this->graphToken();
        $ver = $this->graphVersion();
        $wabaId = (string) (config('services.whatsapp.waba_id') ?: env('WHATSAPP_BUSINESS_ACCOUNT_ID'));

        $resp = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$ver}/{$wabaId}/message_templates", [
                'fields' => implode(',', $fields),
                'limit' => $limit,
            ]);

        if ($resp->failed()) {
            Log::error('[WA] listTemplates failed', ['status' => $resp->status(), 'body' => $resp->body()]);

            return ['data' => []];
        }

        return $resp->json();
    }

    /* ------------------------------------------------------------------
     | Interactive LIST (Netflie)
     |-------------------------------------------------------------------*/
    public function sendList(string $to, string $header, string $body, string $footer, ListAction $action): void
    {
        $this->client->sendList($this->digitsOnly($to), $header, $body, $footer, $action);
    }

    /* ------------------------------------------------------------------
     | Buttons (<=3) with fallback list
     |-------------------------------------------------------------------*/
    public function sendButtonMessage(
        string $to,
        string $question,
        array $buttons,
        ?string $headerText = null,
        ?string $footerText = null
    ): void {
        $norm = array_map(fn ($b) => [
            'id' => (string) ($b['id'] ?? Str::uuid()),
            'title' => (string) ($b['title'] ?? $b['label'] ?? 'Choose'),
            'desc' => (string) ($b['desc'] ?? ''),
        ], $buttons);

        if (count($norm) > 3) {
            $rows = array_map(fn ($b) => [
                'id' => $b['id'],
                'title' => $b['title'],
                'description' => $b['desc'],
            ], $norm);

            $this->sendListRaw(
                $to,
                $headerText ?? '',
                $question,
                __('Show options'),
                [['title' => __('Options'), 'rows' => $rows]],
                $footerText ?? ''
            );

            return;
        }

        $this->sendButtonsRaw($to, $question, $norm, $headerText, $footerText);
    }

    /* ------------------------------------------------------------------
     | Graph Interactive Buttons (<=3)
     |-------------------------------------------------------------------*/
    public function sendButtonsRaw(
        string $to,
        string $bodyText,
        array $buttons,
        ?string $headerText = null,
        ?string $footerText = null
    ): array {
        $btns = array_map(function ($b) {
            return [
                'type' => 'reply',
                'reply' => [
                    'id' => (string) ($b['id'] ?? Str::uuid()),
                    'title' => (string) ($b['title'] ?? $b['label'] ?? 'Choose'),
                ],
            ];
        }, $buttons);

        $interactive = [
            'type' => 'button',
            'body' => ['text' => (string) $bodyText],
            'action' => ['buttons' => $btns],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }
        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $this->digitsOnly($to),
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /* ------------------------------------------------------------------
     | Graph Interactive List
     |-------------------------------------------------------------------*/
    public function sendListRaw(
        string $to,
        string $headerText,
        string $bodyText,
        string $buttonText,
        array $sections,
        string $footerText = ''
    ): array {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => (string) $bodyText],
            'action' => [
                'button' => (string) $buttonText,
                'sections' => array_values($sections),
            ],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }
        if ($footerText !== '') {
            $interactive['footer'] = ['text' => $footerText];
        }

        return $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $this->digitsOnly($to),
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /* ------------------------------------------------------------------
     | Flows (Graph v24.0) – with payload A/B/C retries
     |-------------------------------------------------------------------*/
    public function sendFlow(
        string $to,
        string $flowId,
        string $flowCta,
        string $flowToken,
        string $screen,
        array $data = [],
        string $mode = 'published',
        ?string $headerText = null,
        ?string $bodyText = null,
    ): array {
        $to = $this->digitsOnly($to);

        // Clinic defaults
        $headerText ??= 'Clinic Appointment';
        $bodyText ??= 'Let’s book your appointment.';

        $header = ['type' => 'text', 'text' => Str::limit($headerText, 60, '…')];
        $body = ['text' => $this->normalizeText($bodyText)];

        $dataJson = json_encode($data ?: new \stdClass, JSON_UNESCAPED_UNICODE);

        // A) stringified object with data as JSON string
        $payloadAString = json_encode([
            'screen' => $screen,
            'data' => $dataJson,
        ], JSON_UNESCAPED_UNICODE);

        // B) stringified object with data as object
        $payloadBString = json_encode([
            'screen' => $screen,
            'data' => $data ?: new \stdClass,
        ], JSON_UNESCAPED_UNICODE);

        // C) object form
        $payloadCObject = ['screen' => $screen, 'data' => ($data ?: new \stdClass)];

        $base = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => $header,
                'body' => $body,
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'mode' => $mode,
                        'flow_message_version' => '3',
                        'flow_id' => $flowId,
                        'flow_cta' => $flowCta,
                        'flow_token' => $flowToken,
                        'flow_action' => 'navigate',
                    ],
                ],
            ],
        ];

        // Try A
        try {
            $payload = $base;
            $payload['interactive']['action']['parameters']['flow_action_payload'] = $payloadAString;

            return $this->send($payload);
        } catch (\Throwable $eA) {
            $bodyA = $eA->getMessage();
            $shouldTryB = str_contains($bodyA, '131009') || str_contains($bodyA, 'Parameter value is not valid');
            if (! $shouldTryB) {
                throw $eA;
            }
        }

        // Try B
        try {
            $payload = $base;
            $payload['interactive']['action']['parameters']['flow_action_payload'] = $payloadBString;

            return $this->send($payload);
        } catch (\Throwable $eB) {
            $bodyB = $eB->getMessage();
            $shouldTryC = str_contains($bodyB, '131009') || str_contains($bodyB, 'Parameter value is not valid');
            if (! $shouldTryC) {
                throw $eB;
            }
        }

        // Try C (object)
        $payload = $base;
        $payload['interactive']['action']['parameters']['flow_action_payload'] = $payloadCObject;

        try {
            return $this->send($payload);
        } catch (\Throwable $eC) {
            Log::info('WA Flow send failed after retries', [
                'to' => $to,
                'flowId' => $flowId,
                'error' => $eC->getMessage(),
            ]);
            throw $eC;
        }
    }

    /**
     * Flow INIT via DATA_EXCHANGE variant (forces the INIT call)
     * Clinic version.
     */
    public function sendFlowAppointmentDataExchange(
        string $msisdn,
        string $flowId,
        string $flowToken,
        string $cta,
        string $locale,                 // 'ar' | 'en'
        string $mode = 'published',
        string $version = '3',
        ?string $name = null
    ): array {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $msisdn = $this->digitsOnly($msisdn);

        $defaultClinicName = (string) (config('wa.clinic_name') ?: config('app.name') ?: 'Clinic');

        $headerText = $locale === 'ar'
            ? (config('wa.flows.header_ar', 'حجز موعد'))
            : (config('wa.flows.header_en', 'Clinic Appointment'));
        $headerText = Str::limit($headerText, 60, '…');

        // Welcome body from triggers (single language)
        $welcome = Cache::remember('wa.trigger.welcome', 600, fn () => WhatsappTrigger::where('type', 'welcome')->where('is_active', true)->first()
        );

        $welcomeMsg = $welcome?->getResponseMessage($locale)
            ?: ($locale === 'ar' ? "أهلاً بك في {$defaultClinicName} 🏥" : "Welcome to {$defaultClinicName} 🏥");

        // Replace placeholder if stored message contains {clinic_name}
        $welcomeMsg = str_replace('{clinic_name}', $defaultClinicName, $welcomeMsg);

        $greet = $name
            ? ($locale === 'ar' ? "مرحباً، {$name}!\n" : "Welcome, {$name}!\n")
            : '';

        $bodyText = trim($greet.$welcomeMsg);

        // IMPORTANT: no flow_action_payload for data_exchange (triggers INIT)
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $msisdn,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => ['type' => 'text', 'text' => $headerText],
                'body' => ['text' => $bodyText],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'mode' => $mode,
                        'flow_message_version' => $version,
                        'flow_id' => $flowId,
                        'flow_cta' => $cta,
                        'flow_token' => $flowToken,
                        'flow_action' => 'data_exchange',
                    ],
                ],
            ],
        ];

        return $this->send($payload);
    }

    /**
     * Try to greet by name from whatsapp_sessions; fallback to empty string.
     */
    private function buildGreetingLine(string $msisdn): string
    {
        $msisdn = $this->digitsOnly($msisdn);

        $session = WhatsappSession::where('msisdn', $msisdn)->latest('id')->first();

        $name = $session?->profile_name ?: $session?->name ?: null;

        return $name ? "Welcome, {$name}!\n" : '';
    }

    private function isParamError(\Throwable $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, '131009') || str_contains($m, 'Parameter value is not valid');
    }

    /* ------------------------------------------------------------------
     | Helpers
     |-------------------------------------------------------------------*/
    private function digitsOnly(string $msisdn): string
    {
        return preg_replace('/\D+/', '', $msisdn) ?? $msisdn;
    }

    private function ok(array $json): bool
    {
        return ! isset($json['error']);
    }

    private function normalizeText(string $t): string
    {
        $t = preg_replace('/\\\\n/', "\n", $t);
        $t = preg_replace("/\n{3,}/", "\n\n", $t);

        return trim($t);
    }

    public function uploadMedia(string $localPath, ?string $mime = null): string
    {
        $token = $this->graphToken();
        $version = $this->graphVersion();
        $phoneId = $this->phoneId();

        if (! is_file($localPath)) {
            throw new \RuntimeException("WhatsApp media upload: file not found at {$localPath}");
        }

        $mime ??= mime_content_type($localPath) ?: 'application/octet-stream';

        $endpoint = "https://graph.facebook.com/{$version}/{$phoneId}/media";

        $resp = Http::asMultipart()
            ->withToken($token)
            ->post($endpoint, [
                ['name' => 'messaging_product', 'contents' => 'whatsapp'],
                [
                    'name' => 'file',
                    'contents' => fopen($localPath, 'r'),
                    'filename' => basename($localPath),
                    'headers' => ['Content-Type' => $mime],
                ],
            ]);

        $json = $resp->json();

        if ($resp->failed() || empty($json['id'])) {
            Log::error('[WA] media upload failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            throw new \RuntimeException('WhatsApp media upload failed: '.$resp->body());
        }

        return (string) $json['id'];
    }

    public function sendImageById(string $to, string $mediaId, ?string $caption = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->digitsOnly($to),
            'type' => 'image',
            'image' => ['id' => $mediaId],
        ];
        if ($caption) {
            $payload['image']['caption'] = $this->normalizeText($caption);
        }

        return $this->sendRaw($payload);
    }

    public function sendDocumentById(string $to, string $mediaId, ?string $caption = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->digitsOnly($to),
            'type' => 'document',
            'document' => ['id' => $mediaId],
        ];
        if ($caption) {
            $payload['document']['caption'] = $this->normalizeText($caption);
        }

        return $this->sendRaw($payload);
    }

    public function getGraphToken(): string
    {
        return $this->graphToken();
    }

    public function getGraphVersion(): string
    {
        return $this->graphVersion();
    }

    public function getPhoneId(): string
    {
        return $this->phoneId();
    }

    public function graphGet(string $url, array $query = []): array
    {
        $resp = Http::withToken($this->getGraphToken())
            ->acceptJson()
            ->get($url, $query);

        return [
            'ok' => $resp->successful(),
            'code' => $resp->status(),
            'json' => $resp->json() ?? [],
            'body' => $resp->body(),
        ];
    }
}
