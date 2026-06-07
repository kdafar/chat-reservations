<?php

namespace App\Wa\Services\WhatsApp\Tenant;

use App\Wa\Hub\Models\City;
use App\Wa\Hub\Models\State;
use App\Wa\Hub\Models\WhatsappMessage;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Models\User;
use App\Wa\Models\WhatsApp\WaAccount;
use App\Wa\Models\WhatsApp\WaNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Wave\Setting;

class TenantWhatsAppService
{
    /**
     * Default credentials from config (used as fallback).
     */
    private ?string $defaultApiToken;

    private ?string $defaultPhoneNumberId;

    private ?string $defaultWabaId;

    /**
     * Effective credentials for this instance.
     * Can be overridden per user / per number.
     */
    private ?string $apiToken;

    private ?string $phoneNumberId;

    private ?string $wabaId;

    private ?WaAccount $currentAccount = null;

    private ?WaNumber $currentNumber = null;

    /**
     * Graph API version.
     */
    private string $apiVersion = 'v24.0';

    public function __construct()
    {
        $this->apiVersion = config('services.meta.graph_version', 'v24.0');

        $this->defaultApiToken = config('services.whatsapp.api_token');
        $this->defaultPhoneNumberId = config('services.whatsapp.phone_number_id');

        $this->defaultWabaId = config('services.whatsapp.waba_id');

        $this->apiToken = $this->defaultApiToken;
        $this->phoneNumberId = $this->defaultPhoneNumberId;
        $this->wabaId = $this->defaultWabaId;
    }

    /* ============================================================
     *  CREDENTIAL SELECTION HELPERS
     * ============================================================
     */

    /**
     * Clone the service with specific credentials.
     * Any null values will fall back to the default config values.
     */
    protected function withCredentials(
        ?string $apiToken,
        ?string $phoneNumberId,
        ?string $wabaId = null
    ): self {
        $clone = clone $this;

        $clone->apiToken = $apiToken ?: $this->defaultApiToken;
        $clone->phoneNumberId = $phoneNumberId ?: $this->defaultPhoneNumberId;
        $clone->wabaId = $wabaId ?: $this->defaultWabaId;

        return $clone;
    }

    /**
     * Use WhatsApp settings associated with the *authenticated user*.
     *
     * ⚠️ Adjust this block to match where you store per-customer credentials.
     * For example: columns on users table, or a related model.
     */
    public function forAuthUser(?User $user = null): self
    {
        $user ??= auth()->user();
        if (! $user) {
            return $this; // no auth user → fall back to defaults
        }

        // EXAMPLE ONLY (change to whatever you actually have):
        $apiToken = $user->whatsapp_api_token ?? null;
        $phoneId = $user->whatsapp_phone_number_id ?? null;
        $wabaId = $user->whatsapp_business_acc_id ?? null;

        return $this->withCredentials($apiToken, $phoneId, $wabaId);
    }

    /**
     * Use credentials attached to a specific WaNumber record.
     * Falls back to config if token / waba_id are missing.
     */
    public function forNumber(WaNumber $number): self
    {
        $apiToken = null;

        if ($number->credential && $number->credential->token) {
            try {
                $apiToken = decrypt($number->credential->token);
            } catch (\Throwable $e) {
                Log::warning('[WA] Failed to decrypt wa_credentials token', [
                    'credential_id' => $number->credential->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $wabaId = $number->account?->external_business_id ?: null;

        $clone = $this->withCredentials(
            $apiToken,
            $number->phone_number_id,
            $wabaId,
        );

        $clone->currentNumber = $number;
        $clone->currentAccount = $number->account;

        return $clone;
    }

    /* ============================================================
     *  MAIN SEND ENTRY POINT
     * ============================================================
     */

    /**
     * The main entry point for sending any message.
     * It handles credentials, rate limiting, 24h window and restrictions.
     */
    public function send(string $to, array $payload): void
    {
        if (blank($this->apiToken) || blank($this->phoneNumberId)) {
            Log::error('[WA] Missing API credentials', [
                'phone_number_id' => $this->phoneNumberId,
                'has_token' => ! blank($this->apiToken),
            ]);

            return;
        }

        if ($this->currentAccount && $this->currentAccount->status !== 'active') {
            Log::warning('[WA] Blocked send: account not active', [
                'account_id' => $this->currentAccount->id,
                'status' => $this->currentAccount->status,
            ]);

            return;
        }

        if ($this->currentNumber && $this->currentNumber->status !== 'connected') {
            Log::warning('[WA] Blocked send: number not connected', [
                'wa_number_id' => $this->currentNumber->id,
                'status' => $this->currentNumber->status,
            ]);

            return;
        }

        // Sanitize phone number
        $to = preg_replace('/\D+/', '', $to ?? '');

        if (! $to) {
            Log::warning('[WA] Empty or invalid phone passed to send()', ['payload' => $payload]);

            return;
        }

        // Only templates allowed outside 24h
        if (($payload['type'] ?? null) !== 'template' && $this->isOutside24h($to)) {
            Log::warning('[WA] Outside 24h window; blocking non-template send', [
                'phone' => $to,
                'attempted_type' => $payload['type'] ?? null,
            ]);

            return;
        }

        // 24h+ initiation restriction from settings
        $isRestrictionEnabled = cache()->remember(
            'settings.wa_initiation_restricted',
            3600,
            fn () => (bool) Setting::where('key', 'wa_initiation_restricted')->value('value')
        );

        if ($isRestrictionEnabled) {
            $hasUserInitiated = WhatsappMessage::whereHas('whatsappSession', function ($query) use ($to) {
                $query->where('customer_phone_number', $to);
            })->where('direction', 'incoming')->exists();

            if (! $hasUserInitiated && ($payload['type'] ?? null) !== 'template') {
                Log::warning('[WA] Blocked non-template message to user who has not initiated.', [
                    'phone' => $to,
                    'message_type' => $payload['type'] ?? null,
                ]);

                return;
            }
        }

        // Per-recipient throttling: 10 messages / 5 minutes
        $limiterKey = "wa:msg:$to";
        $maxAttempts = 10;
        $decayInSeconds = 300;

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $this->handleThrottledUser($to, $limiterKey);

            return;
        }

        // For regular messages, hit the rate limiter before sending.
        RateLimiter::hit($limiterKey, $decayInSeconds);

        // Prepare final payload
        $body = ['messaging_product' => 'whatsapp', 'to' => $to] + $payload;

        $this->_makeApiRequest($to, $body);
    }

    /* ============================================================
     *  PUBLIC HELPERS (same signatures as before)
     * ============================================================
     */

    public function sendTextMessage(string $to, string $text, bool $withPreview = true): void
    {
        $this->send($to, [
            'type' => 'text',
            'text' => ['body' => $text, 'preview_url' => $withPreview],
        ]);
    }

    public function sendTemplate(string $to, array $templatePayload): void
    {
        $this->send($to, [
            'type' => 'template',
            'template' => $templatePayload,
        ]);
    }

    public function react(string $to, string $messageId, string $emoji = '👍'): void
    {
        $this->send($to, [
            'type' => 'reaction',
            'reaction' => ['message_id' => $messageId, 'emoji' => $emoji],
        ]);
    }

    public function markRead(string $to, string $messageId): void
    {
        $body = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];

        $this->_makeApiRequest($to, $body);
    }

    public function sendInteractiveButtons(string $to, string $body, array $buttons): void
    {
        $interactiveButtons = [];
        foreach ($buttons as $button) {
            $interactiveButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'],
                    'title' => $button['title'],
                ],
            ];
        }

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => $interactiveButtons],
            ],
        ];

        $this->send($to, $payload);
    }

    public function sendPaymentLinkTemplate(
        string $mobile,
        string $templateName,
        string $locale,
        string $customerName,
        string $orderId,
        string $branchName,
        string $paymentLinkSuffix
    ): void {
        $langCode = $locale === 'ar' ? 'ar' : 'en';

        $this->send($mobile, [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $langCode],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customerName], // {{1}}
                            ['type' => 'text', 'text' => $orderId],      // {{2}}
                            ['type' => 'text', 'text' => $branchName],   // {{3}}
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            ['type' => 'text', 'text' => $paymentLinkSuffix], // {{1}} (URL)
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function sendStateSelectionList(string $to): void
    {
        $states = State::all();

        $rows = $states->map(function ($state) {
            return [
                'id' => 'state_'.$state->id,
                'title' => $state->state_name,
            ];
        })->toArray();

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => 'To find restaurants near you, please select your governorate.'],
                'action' => [
                    'button' => 'Select Governorate',
                    'sections' => [
                        [
                            'title' => 'Governorates',
                            'rows' => $rows,
                        ],
                    ],
                ],
            ],
        ];

        $this->send($to, $payload);
    }

    public function sendCitySelectionList(string $to, int $stateId): void
    {
        $state = State::find($stateId);
        $cities = City::where('state_id', $stateId)->orderBy('city_name')->get();

        if ($cities->isEmpty()) {
            $this->sendTextMessage($to, 'Sorry, no cities are listed for that governorate.');

            return;
        }

        $sections = [];
        foreach ($cities->chunk(10) as $index => $chunk) {
            $rows = $chunk->map(function ($city) {
                return [
                    'id' => 'city_'.$city->id,
                    'title' => $city->city_name,
                ];
            })->toArray();

            $sectionTitle = 'Cities in '.$state->state_name;
            if ($cities->count() > 10) {
                $sectionTitle .= ' (Page '.($index + 1).')';
            }

            $sections[] = [
                'title' => $sectionTitle,
                'rows' => $rows,
            ];
        }

        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => 'Great! Now, please select your city.'],
                'action' => [
                    'button' => 'Select City',
                    'sections' => $sections,
                ],
            ],
        ];

        $this->send($to, $payload);
    }

    /* ============================================================
     *  TEMPLATE MANAGEMENT (used by MetaReviewTools)
     * ============================================================
     */

    public function createSimpleTextTemplate(
        string $name,
        string $category,
        string $language,
        string $bodyText
    ): array {
        $accessToken = $this->apiToken;
        $wabaId = $this->wabaId;

        $version = $this->apiVersion; // same as everywhere else

        if (! $accessToken || ! $wabaId) {
            throw new \RuntimeException('WhatsApp access token or WABA ID not configured.');
        }

        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";

        $payload = [
            'name' => $name,
            'category' => $category,
            'language' => $language,
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $bodyText,
                ],
            ],
        ];

        Log::info('[WA-TOOLS] Creating template', $payload);

        $resp = Http::withToken($accessToken)
            ->acceptJson()
            ->post($url, $payload)
            ->throw();

        $json = $resp->json() ?? [];

        Log::info('[WA-TOOLS] Template create response', $json);

        return $json;
    }

    public function listTemplates(?string $status = 'APPROVED'): array
    {
        $accessToken = $this->apiToken;
        $wabaId = $this->wabaId;
        $version = $this->apiVersion;

        if (! $accessToken || ! $wabaId) {
            throw new \RuntimeException('WhatsApp access token or WABA ID not configured for listing templates.');
        }

        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";

        $query = ['limit' => 50];
        if ($status) {
            $query['status'] = $status;
        }

        $resp = Http::withToken($accessToken)
            ->acceptJson()
            ->get($url, $query)
            ->throw();

        $data = $resp->json('data') ?? [];

        Log::info('[WA-TOOLS] Listed templates from Meta', [
            'count' => count($data),
            'status' => $status,
        ]);

        return $data;
    }

    /* ============================================================
     *  INTERNAL HELPERS
     * ============================================================
     */

    private function isOutside24h(string $to): bool
    {
        $lastIncomingAt = WhatsappMessage::whereHas('whatsappSession', fn ($q) => $q
            ->where('customer_phone_number', $to))
            ->where('direction', 'incoming')
            ->latest('created_at')
            ->value('created_at');

        return ! $lastIncomingAt || now()->diffInHours($lastIncomingAt) >= 24;
    }

    /**
     * Core HTTP call to Meta (used by both send() and markRead()).
     */
    private function _makeApiRequest(string $to, array $body): void
    {
        if (blank($this->apiToken) || blank($this->phoneNumberId)) {
            Log::error('[WA] Cannot call API – credentials missing', [
                'phone' => $to,
                'phone_number_id' => $this->phoneNumberId,
                'has_token' => ! blank($this->apiToken),
            ]);

            return;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );

        try {
            $response = Http::withToken($this->apiToken)
                ->retry(3, 500, throw: false)
                ->post($url, $body)
                ->throw();

            Log::info('[WA] API call successful', [
                'phone' => $to,
                'msg_id' => $response->json('messages.0.id'),
                'status' => $response->status(),
            ]);

            $toDigits = preg_replace('/\D+/', '', $to);

            $session = WhatsappSession::firstOrCreate(
                ['customer_phone_number' => $toDigits],
                [
                    'customer_name' => 'Unknown',
                    'status' => 'active',
                    'locale' => 'en',
                    'last_interacted_at' => now(),
                ]
            );

            $metaMessageId = $response->json('messages.0.id');

            if ($metaMessageId) {
                event(new \App\Events\OutgoingWhatsappMessageSent($session, $body, $metaMessageId));
            } else {
                Log::warning('[WA] No messages[0].id returned; skipping OutgoingWhatsappMessageSent');
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('[WA] API call failed', [
                'phone' => $to,
                'code' => $e->response?->status(),
                'error' => $e->getMessage(),
                'response_body' => $e->response?->json() ?? 'No response body.',
            ]);
        }
    }

    private function handleThrottledUser(string $to, string $limiterKey): void
    {
        $notificationLimiterKey = "wa:notify:$to";

        if (RateLimiter::tooManyAttempts($notificationLimiterKey, 1)) {
            return;
        }

        $retryAfter = RateLimiter::availableIn($limiterKey);
        $waitTime = now()->addSeconds($retryAfter)->diffForHumans(null, true);

        $message = "⚠️ You've sent too many messages in a short period. Please wait {$waitTime} before trying again.";

        Log::warning('[WA] Throttled – too many attempts', [
            'phone' => $to,
            'retry_after' => $retryAfter,
        ]);

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message, 'preview_url' => false],
        ];

        $this->_makeApiRequest($to, $body);
        RateLimiter::hit($notificationLimiterKey, $retryAfter);
    }

    protected function http()
    {
        return Http::withToken($this->apiToken)
            ->baseUrl("https://graph.facebook.com/{$this->apiVersion}")
            ->acceptJson();
    }

    public function syncPhoneInfo(WaNumber $number): WaNumber
    {
        $svc = $this->forNumber($number);

        $res = $svc->http()
            ->get("/{$number->phone_number_id}", [
                'fields' => implode(',', [
                    'id',
                    'display_phone_number',
                    'verified_name',
                    'quality_rating',
                    'messaging_limit_tier',
                    'account_mode',
                ]),
            ])
            ->throw()
            ->json();

        $number->fill([
            'display_phone_number' => $res['display_phone_number'] ?? $number->display_phone_number,
            'verified_name' => $res['verified_name'] ?? $number->verified_name,
            'quality_rating' => $res['quality_rating'] ?? $number->quality_rating,
            'messaging_limit_tier' => $res['messaging_limit_tier'] ?? $number->messaging_limit_tier,
            'account_mode' => $res['account_mode'] ?? $number->account_mode,
            'meta_raw' => $res,
        ])->save();

        return $number;
    }
}
