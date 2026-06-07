<?php

namespace App\Wa\Services;

use App\Wa\Events\OutgoingWhatsappMessageSent;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Services\Media\VideoCompressionService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppBot
{
    public function __construct(
        private readonly GlobalPointService $points,
        private readonly VideoCompressionService $compressor
    ) {}

    /* ─────────────────────────── PUBLIC API ─────────────────────────── */

    public function sendTemplateOrText(
        string $to,
        string $templateName,
        string $language,
        array $components,
        string $fallbackText = '',
        ?int $triggerUserId = null
    ): void {
        $toDigits = preg_replace('/\D+/', '', $to);

        try {
            $this->sendTemplate($toDigits, $templateName, $language, $components, null, $triggerUserId);

            return;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $resp = $e->response ? $e->response->json() : null;
            $code = data_get($resp, 'error.code');

            $isMissingTpl = in_array($code, [132000, 132001, 132005], true);
            $inside24h = ! $this->isOutside24h($toDigits);

            if ($isMissingTpl && $inside24h && $fallbackText !== '') {
                \Log::warning("Template {$templateName} problem (code {$code}) — using text fallback inside 24h");
                $this->sendText($toDigits, $fallbackText);

                return;
            }

            throw $e;
        }
    }

    public function sendTemplate(
        string $to,
        string $templateName,
        string $language,
        array $components,
        ?string $fallbackText = null,
        ?int $triggerUserId = null
    ): void {
        $payload = [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];

        try {
            $this->dispatch($to, $payload, $triggerUserId);
        } catch (RequestException $e) {
            if ($fallbackText) {
                Log::warning('Template send failed, fallback to text: '.$e->getMessage());
                $this->sendText($to, $fallbackText);
            } else {
                throw $e;
            }
        }
    }

    public function sendTextMessage(string $to, string $text): void
    {
        $this->sendText($to, $text);
    }

    public function sendText(string $to, string $text): void
    {
        $toDigits = preg_replace('/\D+/', '', $to);
        if ($this->isOutside24h($toDigits)) {
            \Log::warning('[WA] Blocked plain text outside 24h window', ['to' => $toDigits]);
            throw new \RuntimeException('Outside 24h window; use a template.');
        }

        $this->dispatch($toDigits, [
            'type' => 'text',
            'text' => ['body' => $text, 'preview_url' => true],
        ]);
    }

    public function sendReceipt(
        string $mobile,
        string $pdfUrl,
        string $orderNumber,
        string $locale = 'en'
    ): void {
        $filename = "receipt-{$orderNumber}.pdf";

        $captionEn = "🧾 Receipt for order #{$orderNumber}";
        $captionAr = "🧾 إيصال الطلب رقم #{$orderNumber}";
        $caption = $locale === 'ar'
            ? "{$captionAr}\n{$captionEn}"
            : "{$captionEn}\n{$captionAr}";

        $this->dispatch($mobile, [
            'type' => 'document',
            'document' => [
                'link' => $pdfUrl,
                'filename' => $filename,
                'caption' => $caption,
            ],
        ]);
    }

    public function sendCtaUrlButton(string $to, string $body, string $buttonText, string $url): void
    {
        $this->dispatch($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'cta_url_button',
                                'title' => $buttonText,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->sendText($to, $url);
    }

    public function sendOrderStatusTemplate(
        string $mobile,
        string $templateName,
        string $lang,
        string $headerImageUrl,
        array $bodyParams,
        string $buttonParam,
        string $fallbackText = '',
        ?int $triggerUserId = null
    ): void {
        $languageCode = $lang === 'ar' ? 'ar' : 'en';

        $payload = [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [['type' => 'image', 'image' => ['link' => $headerImageUrl]]],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $bodyParams),
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [['type' => 'text', 'text' => $buttonParam]],
                    ],
                ],
            ],
        ];

        try {
            $this->dispatch($mobile, $payload, $triggerUserId);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if (in_array(data_get($e->response->json(), 'error.code'), [132000, 132001, 132005], true)) {
                $this->sendText($mobile, $fallbackText);
            } else {
                throw $e;
            }
        }
    }

    public function sendRatingFlow(
        string $to,
        string $lang,
        int $restaurantId,
        string $restaurantName,
        string $orderNo
    ): void {
        $flowId = $lang === 'ar'
            ? config('services.whatsapp.order_rating_flow_ar')
            : config('services.whatsapp.order_rating_flow_en');

        $cta = $lang === 'ar' ? 'قيّم طلبك' : 'Rate order';
        $orderDetailsText = "Order #{$orderNo} from {$restaurantName}";

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'body' => ['text' => $cta],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_id' => $flowId,
                        'flow_cta' => $cta,
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => 'RATE',
                            'data' => [
                                'order_details_text' => $orderDetailsText,
                                'restaurant_id' => (string) $restaurantId,
                                'order_number' => $orderNo,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->dispatch($to, $payload);
    }

    public function sendImage(string $to, string $imageUrl, ?string $caption = null): void
    {
        $image = str_starts_with($imageUrl, 'http')
            ? ['link' => $imageUrl]
            : ['id' => $imageUrl];

        if ($caption) {
            $image['caption'] = $caption;
        }

        $this->dispatch($to, [
            'type' => 'image',
            'image' => $image,
        ]);
    }

    /**
     * Upload a local file to Meta and return media_id.
     * Automatically compresses videos (>16MB) and Images (>5MB) via the global service.
     */
    public function uploadImageFile(string $localPath): array
    {
        $pathToSend = $localPath;
        $isTemp = false;

        try {
            if (file_exists($localPath)) {
                $mime = mime_content_type($localPath) ?: '';
                $size = filesize($localPath);

                $LIMIT_VIDEO_16MB = 16 * 1024 * 1024;
                $LIMIT_IMAGE_5MB = 5 * 1024 * 1024;

                // 1. Video Compression
                if (str_starts_with($mime, 'video/') && $size > $LIMIT_VIDEO_16MB) {
                    try {
                        Log::info("[WhatsAppBot] Large video ($size bytes). Compressing...");
                        // Call Global Service
                        $compressed = $this->compressor->compress($localPath);
                        if ($compressed && file_exists($compressed)) {
                            $pathToSend = $compressed;
                            $isTemp = true;
                            Log::info('[WhatsAppBot] Video compression success.');
                        }
                    } catch (\Exception $e) {
                        Log::warning('[WhatsAppBot] Video compression failed: '.$e->getMessage());
                    }
                }

                // 2. Image Compression
                if (str_starts_with($mime, 'image/') && $size > $LIMIT_IMAGE_5MB) {
                    try {
                        Log::info("[WhatsAppBot] Large image ($size bytes). Compressing...");
                        // Call Global Service
                        $compressed = $this->compressor->compressImage($localPath);
                        if ($compressed && file_exists($compressed)) {
                            $pathToSend = $compressed;
                            $isTemp = true;
                            Log::info('[WhatsAppBot] Image compression success.');
                        }
                    } catch (\Exception $e) {
                        Log::warning('[WhatsAppBot] Image compression failed: '.$e->getMessage());
                    }
                }
            }

            $token = config('services.whatsapp.api_token');
            $phoneId = config('services.whatsapp.phone_number_id');

            $resp = Http::asMultipart()
                ->withToken($token)
                ->attach('file', file_get_contents($pathToSend), basename($pathToSend))
                ->attach('messaging_product', 'whatsapp')
                ->post("https://graph.facebook.com/v24.0/{$phoneId}/media")
                ->throw();

            $mediaId = data_get($resp->json(), 'id');
            if (! $mediaId) {
                return [false, null, 'No media id returned'];
            }

            return [true, $mediaId, null];

        } catch (RequestException $e) {
            $body = optional($e->response)->json();
            Log::warning('WA media upload failed', ['err' => $body ?: $e->getMessage()]);

            return [false, null, $e->getMessage()];
        } catch (\Throwable $e) {
            return [false, null, $e->getMessage()];
        } finally {
            if ($isTemp && file_exists($pathToSend)) {
                @unlink($pathToSend);
            }
        }
    }

    public function sendImageById(string $to, string $mediaId, ?string $caption = null): void
    {
        $image = ['id' => $mediaId];
        if ($caption) {
            $image['caption'] = $caption;
        }

        $this->dispatch($to, [
            'type' => 'image',
            'image' => $image,
        ]);
    }

    public function sendNormalizedImage(string $to, string $imageUrl, ?string $caption = null): array
    {
        $cacheKey = 'wa_media_id:'.sha1($imageUrl);
        if ($mediaId = Cache::get($cacheKey)) {
            $this->sendImageById($to, $mediaId, $caption);

            return [true, null, null];
        }

        /** @var \App\Services\ImageNormalizer $norm */
        $norm = app(\App\Services\ImageNormalizer::class);
        [$ok, $local, $err] = $norm->toLocalJpeg($imageUrl);
        if (! $ok) {
            return [false, null, "Normalize failed: $err"];
        }

        [$okUp, $mediaId, $errUp] = $this->uploadImageFile($local);
        @unlink($local);
        if (! $okUp) {
            return [false, null, "Upload failed: $errUp"];
        }

        Cache::put($cacheKey, $mediaId, now()->addDays(7));
        $this->sendImageById($to, $mediaId, $caption);

        return [true, null, null];
    }

    public function uploadImageFromLink(string $imageUrl): array
    {
        try {
            $cacheKey = 'wa_media_id:'.sha1($imageUrl);
            if ($mid = \Illuminate\Support\Facades\Cache::get($cacheKey)) {
                return [true, $mid, null];
            }

            /** @var \App\Services\ImageNormalizer $norm */
            $norm = app(\App\Services\ImageNormalizer::class);
            [$ok, $localPath, $err] = $norm->toLocalJpeg($imageUrl);
            if (! $ok) {
                return [false, null, "Normalize failed: $err"];
            }

            [$okUp, $mediaId, $errUp] = $this->uploadImageFile($localPath);
            @unlink($localPath);

            if (! $okUp || ! $mediaId) {
                return [false, null, "Upload failed: $errUp"];
            }

            \Illuminate\Support\Facades\Cache::put($cacheKey, $mediaId, now()->addDays(7));

            return [true, $mediaId, null];

        } catch (\Throwable $e) {
            return [false, null, $e->getMessage()];
        }
    }

    private function isOutside24h(string $to): bool
    {
        $num = preg_replace('/\D+/', '', $to);

        $last = \App\Hub\Models\WhatsappMessage::whereHas(
            'whatsappSession',
            fn ($q) => $q->where('customer_phone_number', $num)
        )
            ->where('direction', 'incoming')
            ->latest('created_at')
            ->value('created_at');

        return ! $last || now()->diffInHours($last) >= 24;
    }

    public function sendHospitalReminderImage(
        string $to,
        string $lang,
        string $imageUrlOrMediaId,
        array $bodyParams = [],
        string $fallbackText = '',
        ?int $triggerUserId = null
    ): void {
        $templateName = $lang === 'ar'
            ? 'hospital_reminder_image_v1_ar'
            : 'hospital_reminder_image_v1_en';

        $waLang = $lang === 'ar' ? 'ar' : 'en';

        $headerParam = preg_match('~^https?://~', $imageUrlOrMediaId)
            ? ['type' => 'image', 'image' => ['link' => $imageUrlOrMediaId]]
            : ['type' => 'image', 'image' => ['id' => $imageUrlOrMediaId]];

        $components = [
            ['type' => 'header', 'parameters' => [$headerParam]],
            ['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $bodyParams)],
        ];

        $this->sendTemplateOrText(
            $to,
            $templateName,
            $waLang,
            $components,
            $fallbackText,
            $triggerUserId
        );
    }

    public function sendFleetAlertTemplate(
        string $mobile,
        string $templateName,
        string $lang,
        string $alertTitle,
        array $bodyParams,
        ?string $mapParam,
        string $fallbackText
    ): void {
        $sanitize = function (?string $val): ?string {
            if ($val === null) {
                return null;
            }
            $val = preg_replace('/[\r\n\t]+/', ' ', $val);
            $val = preg_replace('/ {2,}/', ' ', $val);

            return trim($val);
        };

        $alertTitle = $sanitize($alertTitle) ?? '';
        $cleanBodyParams = array_map(fn ($v) => $sanitize((string) $v) ?? '', $bodyParams);
        $cleanMapParam = $mapParam !== null ? $sanitize($mapParam) : null;

        $components = [
            [
                'type' => 'header',
                'parameters' => [['type' => 'text', 'text' => $alertTitle]],
            ],
            [
                'type' => 'body',
                'parameters' => array_map(fn ($val) => ['type' => 'text', 'text' => (string) $val], $cleanBodyParams),
            ],
        ];

        if ($cleanMapParam) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $cleanMapParam]],
            ];
        }

        $this->sendTemplateOrText($mobile, $templateName, $lang, $components, $fallbackText);
    }

    public function sendFleetFuelReportTemplate(
        string $mobile,
        string $templateName,
        string $lang,
        array $bodyParams,
        string $pdfUrl,
        ?string $fallbackText = null
    ): void {
        $base = $templateName ?: 'fleet_fuel_report';

        if ($lang === 'ar') {
            if ($base === 'fleet_fuel_report_en') {
                $finalName = 'fleet_fuel_report_ar';
            } elseif ($base === 'fleet_fuel_report_ar') {
                $finalName = $base;
            } else {
                $finalName = $base.'_ar';
            }
            $languageCode = 'ar';
        } else {
            if ($base === 'fleet_fuel_report_ar') {
                $finalName = 'fleet_fuel_report_en';
            } elseif ($base === 'fleet_fuel_report_en') {
                $finalName = $base;
            } else {
                $finalName = $base.'_en';
            }
            $languageCode = 'en';
        }

        $components = [
            [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'document',
                        'document' => ['link' => $pdfUrl, 'filename' => 'weekly-fuel-report.pdf'],
                    ],
                ],
            ],
            [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $bodyParams),
            ],
        ];

        $payload = [
            'type' => 'template',
            'template' => [
                'name' => $finalName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        try {
            $this->dispatch($mobile, $payload);
        } catch (RequestException $e) {
            Log::error('[WA] Fleet fuel report template failed', [
                'to' => $mobile,
                'name' => $finalName,
                'status' => optional($e->response)->status(),
            ]);

            if ($fallbackText) {
                try {
                    $this->sendText($mobile, $fallbackText);
                } catch (\Throwable $inner) {
                    Log::error('[WA] Fleet fuel report fallback text failed');
                }
            } else {
                throw $e;
            }
        }
    }

    public function sendTemplateByName(
        string $to,
        string $templateName,
        array $bodyParams = [],
        ?string $fallbackText = null,
        ?int $triggerUserId = null
    ): void {
        $tpl = MessageTemplate::where('name', $templateName)->firstOrFail();

        if (strtoupper((string) $tpl->status) !== 'APPROVED') {
            throw new \RuntimeException("Template {$tpl->name} is not approved.");
        }

        $components = [];

        if ($tpl->campaign_media_url) {
            $header = collect($tpl->components ?? [])->first(fn ($c) => strtoupper($c['type'] ?? '') === 'HEADER');
            $format = strtoupper($header['format'] ?? '');

            if ($format === 'IMAGE') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [['type' => 'image', 'image' => ['link' => $tpl->campaign_media_url]]],
                ];
            } elseif ($format === 'VIDEO') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [['type' => 'video', 'video' => ['link' => $tpl->campaign_media_url]]],
                ];
            }
        }

        if (! empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $bodyParams),
            ];
        }

        $this->sendTemplateOrText(
            $to,
            $tpl->name,
            (string) ($tpl->language ?: 'en'),
            $components,
            $fallbackText ?: ($tpl->body_preview ?: ''),
            $triggerUserId
        );
    }

    private function templateCostFromDb(string $templateName): int
    {
        return (int) (MessageTemplate::where('name', $templateName)->value('points_cost') ?? 1);
    }

    private function ensureTemplateBalance(?int $triggerUserId, int $cost, array $meta = []): void
    {
        if ($cost <= 0) {
            return;
        }

        if (! $this->points->hasSystemBalance($cost)) {
            Log::warning('[WA] Not enough global points', ['cost' => $cost]);
            throw new \RuntimeException('Insufficient global points to send WhatsApp template.');
        }
    }

    private function dispatch(string $to, array $payload, ?int $triggerUserId = null): void
    {
        $toDigits = preg_replace('/\D+/', '', $to);

        $body = array_merge([
            'messaging_product' => 'whatsapp',
            'to' => $toDigits,
        ], $payload);

        $templateName = data_get($body, 'template.name');
        $isTemplate = (($body['type'] ?? null) === 'template') && is_string($templateName) && $templateName !== '';

        $templateCost = 0;
        if ($isTemplate) {
            $templateCost = $this->templateCostFromDb($templateName);
            $this->ensureTemplateBalance($triggerUserId, $templateCost, ['template' => $templateName]);
        }

        Log::debug('WA-REQUEST', $body);

        $resp = Http::withToken(config('services.whatsapp.api_token'))
            ->post(
                'https://graph.facebook.com/v24.0/'.
                config('services.whatsapp.phone_number_id').'/messages',
                $body
            );

        Log::debug('WA-RESPONSE', $resp->json());
        $resp->throw();

        $metaMessageId = data_get($resp->json(), 'messages.0.id');

        if ($isTemplate && $templateCost > 0) {
            $this->points->deductSystemPoints($triggerUserId, $templateCost, 'template_message', [
                'to' => $toDigits,
                'template' => $templateName,
                'wamid' => $metaMessageId,
                'language' => data_get($body, 'template.language.code'),
            ]);
        }

        if ($metaMessageId) {
            $session = WhatsappSession::firstOrCreate(
                ['customer_phone_number' => $toDigits],
                [
                    'customer_name' => 'Unknown',
                    'status' => 'active',
                    'locale' => 'en',
                    'last_interacted_at' => now(),
                ]
            );

            event(new OutgoingWhatsappMessageSent($session, $body, $metaMessageId));
        }
    }

    public function sendPointInvoice(string $mobile, string $pdfUrl, string $invoiceNo, string $locale = 'en'): void
    {
        $filename = "invoice-{$invoiceNo}.pdf";

        $captionEn = "🧾 Invoice {$invoiceNo} (Points Purchase)";
        $captionAr = "🧾 فاتورة {$invoiceNo} (شراء نقاط)";

        $caption = $locale === 'ar'
            ? "{$captionAr}\n{$captionEn}"
            : "{$captionEn}\n{$captionAr}";

        $this->dispatch($mobile, [
            'type' => 'document',
            'document' => [
                'link' => $pdfUrl,
                'filename' => $filename,
                'caption' => $caption,
            ],
        ]);
    }
}
