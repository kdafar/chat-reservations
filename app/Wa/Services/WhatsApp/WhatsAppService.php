<?php

namespace App\Wa\Services\WhatsApp;

use App\Wa\Events\OutgoingWhatsappMessageSent;
use App\Wa\Hub\Models\City;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\State;
use App\Wa\Hub\Models\WhatsappMessage;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Services\GlobalPointService;
use App\Wa\Services\Media\VideoCompressionService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Wave\Setting;

class WhatsAppService
{
    private string $apiToken;

    private string $phoneNumberId;

    private string $apiVersion = 'v24.0';

    private GlobalPointService $points;

    private VideoCompressionService $compressor;

    public function __construct(GlobalPointService $points, VideoCompressionService $compressor)
    {
        $this->points = $points;
        $this->compressor = $compressor;
        $this->apiToken = config('services.whatsapp.api_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }

    private function wabaId(): string
    {
        $wabaId = config('services.whatsapp.waba_id');

        if (! $wabaId) {
            throw new \RuntimeException('Missing WABA ID. Set services.whatsapp.waba_id (WHATSAPP_WABA_ID).');
        }

        return $wabaId;
    }

    private function graphVersion(): string
    {
        return config('services.meta.graph_version', $this->apiVersion ?: 'v24.0');
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.ltrim($url, '/');
        }

        return $url;
    }

    private function validateBodyVariablesStrict(string $bodyText): int
    {
        preg_match_all('/{{\s*(\d+)\s*}}/', $bodyText, $m);

        $nums = array_map('intval', $m[1] ?? []);
        $nums = array_values(array_unique($nums));
        sort($nums);

        if (empty($nums)) {
            return 0;
        }

        if ($nums[0] !== 1) {
            throw new \RuntimeException('Variables must start from {{1}}.');
        }

        $expected = range(1, count($nums));
        if ($nums !== $expected) {
            $used = implode(', ', array_map(fn ($n) => "{{{$n}}}", $nums));
            throw new \RuntimeException("Variable numbering must be sequential ({{1}}, {{2}}, ...). You used: {$used}.");
        }

        return max($nums);
    }

    private function buildButtonsStrict(array $buttonsData): array
    {
        if (empty($buttonsData)) {
            return [];
        }

        $rows = collect($buttonsData)->filter(fn ($b) => is_array($b))->values();

        $types = $rows->pluck('type')->filter()->unique()->values();
        $hasQuick = $types->contains('QUICK_REPLY');

        if ($hasQuick && $types->count() > 1) {
            throw new \RuntimeException('You cannot mix Quick Reply buttons with URL/Phone buttons.');
        }

        if ($hasQuick && $rows->count() > 3) {
            throw new \RuntimeException('Maximum 3 Quick Reply buttons allowed.');
        }

        if (! $hasQuick && $rows->count() > 2) {
            throw new \RuntimeException('Maximum 2 buttons allowed for URL/Phone buttons.');
        }

        $buttons = [];
        foreach ($rows as $btn) {
            $type = (string) ($btn['type'] ?? '');
            $text = trim((string) ($btn['text'] ?? ''));

            if ($type === '' || $text === '') {
                throw new \RuntimeException('Each button must have a type and text.');
            }

            if (mb_strlen($text) > 25) {
                throw new \RuntimeException('Button text must be 25 characters or less.');
            }

            if ($type === 'QUICK_REPLY') {
                $buttons[] = ['type' => 'QUICK_REPLY', 'text' => $text];

                continue;
            }

            if ($type === 'PHONE_NUMBER') {
                $phone = trim((string) ($btn['phone_number'] ?? ''));
                if ($phone === '') {
                    throw new \RuntimeException('Phone Number button requires phone_number.');
                }
                if (! preg_match('/^\+?[0-9]{6,18}$/', $phone)) {
                    throw new \RuntimeException('Phone number must be digits (optionally starting with +).');
                }

                $buttons[] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => $text,
                    'phone_number' => $phone,
                ];

                continue;
            }

            if ($type === 'URL') {
                $url = $this->normalizeUrl((string) ($btn['url'] ?? ''));
                if ($url === '') {
                    throw new \RuntimeException('URL button requires url.');
                }

                $buttons[] = [
                    'type' => 'URL',
                    'text' => $text,
                    'url' => $url,
                ];

                continue;
            }

            throw new \RuntimeException("Invalid button type: {$type}");
        }

        return $buttons;
    }

    public function send(string $to, array $payload, ?int $triggerUserId = null): ?array
    {
        if (blank($this->apiToken) || blank($this->phoneNumberId)) {
            Log::error('[WA] Missing API credentials');

            return null;
        }

        $to = preg_replace('/\D+/', '', $to);

        if (($payload['type'] ?? null) !== 'template' && $this->isOutside24h($to)) {
            Log::warning('[WA] Outside 24h window; blocking non-template send', [
                'phone' => $to,
                'attempted_type' => $payload['type'] ?? null,
            ]);

            return null;
        }

        $isRestrictionEnabled = cache()->remember('settings.wa_initiation_restricted', 3600, function () {
            return (bool) Setting::where('key', 'wa_initiation_restricted')->value('value');
        });

        if ($isRestrictionEnabled) {
            $hasUserInitiated = WhatsappMessage::whereHas('whatsappSession', function ($query) use ($to) {
                $query->where('customer_phone_number', $to);
            })->where('direction', 'incoming')->exists();

            if (! $hasUserInitiated && ($payload['type'] ?? null) !== 'template') {
                Log::warning('[WA] Blocked sending non-template message to user who has not initiated contact.', [
                    'phone' => $to,
                    'message_type' => $payload['type'] ?? null,
                ]);

                return null;
            }
        }

        $templateName = data_get($payload, 'template.name');
        $isTemplate = (($payload['type'] ?? null) === 'template') && is_string($templateName) && $templateName !== '';
        $templateCost = 0;

        if ($isTemplate) {
            // FIX: Auto-process payload for media links (compress & upload)
            $payload['template'] = $this->preprocessTemplatePayload($payload['template']);

            $templateCost = $this->templateCost($templateName);
            $this->ensurePoints($templateCost, [
                'template' => $templateName,
                'to' => $to,
                'source' => 'messages',
            ], $triggerUserId);
        }

        $limiterKey = "wa:msg:$to";
        $maxAttempts = 10;
        $decayInSeconds = 300;

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $this->handleThrottledUser($to, $limiterKey);

            return null;
        }

        RateLimiter::hit($limiterKey, $decayInSeconds);

        $body = ['messaging_product' => 'whatsapp', 'to' => $to] + $payload;

        $res = $this->_makeApiRequest($to, $body);

        if ($isTemplate && $templateCost > 0 && $res && data_get($res, 'messages.0.id')) {
            $this->points->deductSystemPoints($triggerUserId, $templateCost, 'template_message', [
                'to' => $to,
                'template' => $templateName,
                'wamid' => data_get($res, 'messages.0.id'),
                'language' => data_get($payload, 'template.language.code'),
                'endpoint' => 'messages',
            ]);
        }

        return $res;
    }

    public function sendTextMessage(string $to, string $text, bool $withPreview = true): ?array
    {
        return $this->send($to, [
            'type' => 'text',
            'text' => ['body' => $text, 'preview_url' => $withPreview],
        ]);
    }

    public function sendTemplate(string $to, array $templatePayload, ?int $triggerUserId = null): ?array
    {
        return $this->send($to, ['type' => 'template', 'template' => $templatePayload], $triggerUserId);
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

    private function handleThrottledUser(string $to, string $limiterKey): void
    {
        $notificationLimiterKey = "wa:notify:$to";
        if (RateLimiter::tooManyAttempts($notificationLimiterKey, 1)) {
            return;
        }

        $retryAfter = RateLimiter::availableIn($limiterKey);
        $waitTime = now()->addSeconds($retryAfter)->diffForHumans(null, true);

        $message = "⚠️ You've sent too many messages in a short period. Please wait {$waitTime} before trying again.";

        Log::warning('[WA] Throttled – too many attempts', ['phone' => $to, 'retry_after' => $retryAfter]);

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message, 'preview_url' => false],
        ];

        $this->_makeApiRequest($to, $body);
        RateLimiter::hit($notificationLimiterKey, $retryAfter);
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
                'action' => [
                    'buttons' => $interactiveButtons,
                ],
            ],
        ];

        $this->send($to, $payload);
    }

    private function isOutside24h(string $to): bool
    {
        $lastIncomingAt = WhatsappMessage::whereHas('whatsappSession', fn ($q) => $q->where('customer_phone_number', $to))
            ->where('direction', 'incoming')
            ->latest('created_at')
            ->value('created_at');

        return ! $lastIncomingAt || now()->diffInHours($lastIncomingAt) >= 24;
    }

    private function _makeApiRequest(string $to, array $body): ?array
    {
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

            $json = $response->json();

            Log::info('[WA] API call successful', [
                'phone' => $to,
                'msg_id' => $json['messages'][0]['id'] ?? null,
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

            $metaMessageId = $json['messages'][0]['id'] ?? null;
            if ($metaMessageId) {
                event(new OutgoingWhatsappMessageSent($session, $body, $metaMessageId));
            } else {
                Log::warning('[WA] No messages[0].id returned; skipping OutgoingWhatsappMessageSent');
            }

            return $json ?? [];

        } catch (RequestException $e) {
            Log::error('[WA] API call failed', [
                'phone' => $to,
                'code' => $e->response?->status(),
                'error' => $e->getMessage(),
                'response_body' => $e->response?->json() ?? 'No response body.',
            ]);

            return null;
        }
    }

    public function sendPaymentLinkTemplate(
        string $mobile,
        string $templateName,
        string $locale,
        string $customerName,
        string $orderId,
        string $branchName,
        string $paymentLinkSuffix,
        ?int $triggerUserId = null
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
                            ['type' => 'text', 'text' => $customerName],
                            ['type' => 'text', 'text' => $orderId],
                            ['type' => 'text', 'text' => $branchName],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            ['type' => 'text', 'text' => $paymentLinkSuffix],
                        ],
                    ],
                ],
            ],
        ], $triggerUserId);
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
                    'sections' => [['title' => 'Governorates', 'rows' => $rows]],
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
                return ['id' => 'city_'.$city->id, 'title' => $city->city_name];
            })->toArray();

            $sectionTitle = 'Cities in '.$state->state_name;
            if ($cities->count() > 10) {
                $sectionTitle .= ' (Page '.($index + 1).')';
            }

            $sections[] = ['title' => $sectionTitle, 'rows' => $rows];
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

    public function createSimpleTextTemplate(string $name, string $category, string $language, string $bodyText): array
    {
        $payload = [
            'name' => $name,
            'category' => $category,
            'language' => $language,
            'components' => [
                ['type' => 'BODY', 'text' => $bodyText],
            ],
        ];

        $version = $this->graphVersion();
        $wabaId = $this->wabaId();

        $resp = Http::withToken($this->apiToken)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", $payload);

        $resp->throw();

        return $resp->json() ?? [];
    }

    public function listTemplates(?string $status = null): array
    {
        $accessToken = $this->apiToken;
        $wabaId = $this->wabaId();
        $version = $this->graphVersion();

        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";

        $query = ['limit' => 200];
        if ($status) {
            $query['status'] = $status;
        }

        $resp = Http::withToken($accessToken)
            ->acceptJson()
            ->get($url, $query);

        $resp->throw();

        $data = $resp->json('data') ?? [];

        Log::info('[WA-TOOLS] Listed templates from Meta', [
            'count' => count($data),
            'status' => $status,
        ]);

        return $data;
    }

    public function getCurrentNumberHealth(): array
    {
        if (blank($this->apiToken) || blank($this->phoneNumberId)) {
            return [
                'status' => 'missing_credentials',
                'display_phone_number' => null,
                'verified_name' => null,
                'quality_rating' => null,

                // Backward compatible keys
                'messaging_limit_tier' => null,
                'name_status' => null,
                'code_verification_status' => null,
                'platform_type' => null,
                'throughput_level' => null,

                // ✅ Add-only (new keys)
                'whatsapp_business_manager_messaging_limit' => null,
                'effective_messaging_limit_tier' => null,
            ];
        }

        $cacheKey = "wa_phone_health_{$this->wabaId()}_{$this->phoneNumberId}";

        return cache()->remember($cacheKey, now()->addMinutes(5), function () {
            $version = $this->graphVersion();

            // 1) Phone-node (deprecated for tier, still useful for code_verification_status, webhook, etc.)
            $phoneNode = $this->fetchPhoneNodeHealth($version);

            // 2) WABA phone_numbers list (may include tier but can be deprecated)
            $wabaNode = $this->fetchWabaPhoneEntry($version);

            // 3) Business/WABA enforcement limit (NEW canonical source)
            $mgrNode = $this->fetchBusinessMessagingLimit($version); // returns whatsapp_business_manager_messaging_limit

            // Merge with "fills gaps" rule
            $displayPhone = $phoneNode['display_phone_number'] ?? $wabaNode['display_phone_number'] ?? null;
            $verifiedName = $phoneNode['verified_name'] ?? $wabaNode['verified_name'] ?? null;

            // Prefer WABA phone_numbers for quality + phone tier (more consistent than phone node)
            $quality = $wabaNode['quality_rating'] ?? ($phoneNode['quality_rating'] ?? null);

            // Phone-tier: keep a separate debug field
            $phoneTier = $wabaNode['messaging_limit_tier'] ?? ($phoneNode['messaging_limit_tier'] ?? null);

            // ✅ Canonical enforcement tier (manager)
            $mgrTier = $mgrNode['whatsapp_business_manager_messaging_limit'] ?? null;

            // Effective tier decision (what your UI should treat as current):
            // Prefer enforcement tier if present; otherwise fall back to phone tier.
            $effectiveTier = $mgrTier ?: $phoneTier;

            // Preserve your special-case tier bump
            if ($effectiveTier === 'TIER_1K' && ! empty($verifiedName)) {
                $effectiveTier = 'TIER_2K';
            }

            $nameStatus = $phoneNode['name_status'] ?? null;
            if (blank($nameStatus) && ! blank($wabaNode['name_status'] ?? null)) {
                $nameStatus = $wabaNode['name_status'];
            }

            $codeStatus = $phoneNode['code_verification_status'] ?? null;

            $platform = $phoneNode['platform_type'] ?? null;
            if (blank($platform) && ! blank($wabaNode['platform_type'] ?? null)) {
                $platform = $wabaNode['platform_type'];
            }

            $throughputLevel = $phoneNode['throughput_level'] ?? null;
            if (blank($throughputLevel) && ! blank($wabaNode['throughput_level'] ?? null)) {
                $throughputLevel = $wabaNode['throughput_level'];
            }

            // Status decision:
            // ok if at least one call succeeded (phone, waba, or mgr)
            $status = 'ok';
            $okAny = (($phoneNode['_status'] ?? 'error') === 'ok')
                || (($wabaNode['_status'] ?? 'error') === 'ok')
                || (($mgrNode['_status'] ?? 'error') === 'ok');

            if (! $okAny) {
                $status = 'error';
            }

            return [
                'status' => $status,
                'display_phone_number' => $displayPhone,
                'verified_name' => $verifiedName,
                'quality_rating' => $quality,

                // Backward compatible: now means EFFECTIVE tier
                'messaging_limit_tier' => $effectiveTier,

                'name_status' => $nameStatus,
                'code_verification_status' => $codeStatus,
                'platform_type' => $platform,
                'throughput_level' => $throughputLevel,

                // ✅ Add-only debug + canonical fields
                'whatsapp_business_manager_messaging_limit' => $mgrTier,
                'effective_messaging_limit_tier' => $effectiveTier,

                // NEW: explicit debug fields for mismatch display
                'phone_messaging_limit_tier' => $phoneTier,
                'enforced_messaging_limit_tier' => $mgrTier,
            ];
        });
    }

    /**
     * The WhatsApp Business account's own profile picture URL (the avatar shown
     * to customers in chat). Cached — it's an external Graph call. Returns null
     * if not configured or none is set.
     */
    public function getBusinessProfilePictureUrl(): ?string
    {
        if (! $this->apiToken || ! $this->phoneNumberId) {
            return null;
        }

        return Cache::remember("wa_profile_pic_{$this->phoneNumberId}", now()->addHours(6), function () {
            try {
                $version = $this->graphVersion();
                $resp = Http::withToken($this->apiToken)->acceptJson()
                    ->get("https://graph.facebook.com/{$version}/{$this->phoneNumberId}/whatsapp_business_profile", [
                        'fields' => 'profile_picture_url',
                    ])->throw();

                return data_get($resp->json(), 'data.0.profile_picture_url');
            } catch (\Throwable $e) {
                Log::warning('[WA] Business profile picture fetch failed', [
                    'phone_number_id' => $this->phoneNumberId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Fetch health data from /{phoneNumberId}.
     * Returns keys matching the final payload + internal _status marker.
     */
    private function fetchPhoneNodeHealth(string $version): array
    {
        $url = "https://graph.facebook.com/{$version}/{$this->phoneNumberId}";

        try {
            $resp = Http::withToken($this->apiToken)
                ->acceptJson()
                ->get($url, [
                    'fields' => 'display_phone_number,verified_name,quality_rating,messaging_limit_tier,name_status,code_verification_status,platform_type,throughput',
                ])
                ->throw();

            $data = $resp->json() ?? [];

            return [
                '_status' => 'ok',
                'display_phone_number' => $data['display_phone_number'] ?? null,
                'verified_name' => $data['verified_name'] ?? null,
                'quality_rating' => $data['quality_rating'] ?? null,
                'messaging_limit_tier' => $data['messaging_limit_tier'] ?? null,
                'name_status' => $data['name_status'] ?? null,
                'code_verification_status' => $data['code_verification_status'] ?? null,
                'platform_type' => $data['platform_type'] ?? null,
                'throughput_level' => data_get($data, 'throughput.level'),
            ];
        } catch (\Throwable $e) {
            Log::warning('[WA] Phone node health fetch failed', [
                'phone_number_id' => $this->phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return [
                '_status' => 'error',
                'display_phone_number' => null,
                'verified_name' => null,
                'quality_rating' => null,
                'messaging_limit_tier' => null,
                'name_status' => null,
                'code_verification_status' => null,
                'platform_type' => null,
                'throughput_level' => null,
            ];
        }
    }

    /**
     * Fetch matching phone entry from /{wabaId}/phone_numbers and return same shape + internal _status marker.
     */
    private function fetchWabaPhoneEntry(string $version): array
    {
        try {
            $wabaId = $this->wabaId();
        } catch (\Throwable $e) {
            // WABA id missing should not kill phone-node health.
            Log::warning('[WA] Missing WABA ID for health merge', [
                'error' => $e->getMessage(),
            ]);

            return [
                '_status' => 'error',
                'display_phone_number' => null,
                'verified_name' => null,
                'quality_rating' => null,
                'messaging_limit_tier' => null,
                'name_status' => null,
                'platform_type' => null,
                'throughput_level' => null,
            ];
        }

        $url = "https://graph.facebook.com/{$version}/{$wabaId}/phone_numbers";

        try {
            $resp = Http::withToken($this->apiToken)
                ->acceptJson()
                ->get($url, [
                    'fields' => 'id,display_phone_number,quality_rating,messaging_limit_tier,name_status,verified_name,platform_type,throughput',
                    'limit' => 200,
                ])
                ->throw();

            $rows = $resp->json('data') ?? [];
            $match = collect($rows)->firstWhere('id', $this->phoneNumberId);

            if (! is_array($match)) {
                return [
                    '_status' => 'ok',
                    'display_phone_number' => null,
                    'verified_name' => null,
                    'quality_rating' => null,
                    'messaging_limit_tier' => null,
                    'name_status' => null,
                    'platform_type' => null,
                    'throughput_level' => null,
                ];
            }

            return [
                '_status' => 'ok',
                'display_phone_number' => $match['display_phone_number'] ?? null,
                'verified_name' => $match['verified_name'] ?? null,
                'quality_rating' => $match['quality_rating'] ?? null,
                'messaging_limit_tier' => $match['messaging_limit_tier'] ?? null,
                'name_status' => $match['name_status'] ?? null,
                'platform_type' => $match['platform_type'] ?? null,
                'throughput_level' => data_get($match, 'throughput.level'),
            ];
        } catch (\Throwable $e) {
            Log::warning('[WA] WABA phone_numbers fetch failed', [
                'waba_id' => $wabaId ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                '_status' => 'error',
                'display_phone_number' => null,
                'verified_name' => null,
                'quality_rating' => null,
                'messaging_limit_tier' => null,
                'name_status' => null,
                'platform_type' => null,
                'throughput_level' => null,
            ];
        }
    }

    /**
     * Fetch business/WABA manager enforcement limit.
     * Canonical source for current messaging limit tier.
     */
    private function fetchBusinessMessagingLimit(string $version): array
    {
        try {
            // IMPORTANT:
            // Meta support told you: GET /{business-id}?fields=whatsapp_business_manager_messaging_limit
            // In your case you are using the WABA id here (same as in your curl).
            $businessId = $this->wabaId();
        } catch (\Throwable $e) {
            Log::warning('[WA] Missing business/WABA ID for manager messaging limit', [
                'error' => $e->getMessage(),
            ]);

            return [
                '_status' => 'error',
                'whatsapp_business_manager_messaging_limit' => null,
            ];
        }

        $url = "https://graph.facebook.com/{$version}/{$businessId}";

        try {
            $resp = Http::withToken($this->apiToken)
                ->acceptJson()
                ->get($url, [
                    'fields' => 'whatsapp_business_manager_messaging_limit',
                ])
                ->throw();

            $data = $resp->json() ?? [];

            return [
                '_status' => 'ok',
                'whatsapp_business_manager_messaging_limit' => $data['whatsapp_business_manager_messaging_limit'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('[WA] Manager messaging limit fetch failed', [
                'business_id' => $businessId ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                '_status' => 'error',
                'whatsapp_business_manager_messaging_limit' => null,
            ];
        }
    }

    public function sendMarketingTemplate(string $to, array $templatePayload, ?int $triggerUserId = null): ?array
    {
        if (blank($this->apiToken) || blank($this->phoneNumberId)) {
            Log::error('[WA-MARKETING] Missing API credentials');

            return null;
        }

        $to = preg_replace('/\D+/', '', $to);

        $version = $this->apiVersion ?: 'v24.0';
        $url = "https://graph.facebook.com/{$version}/{$this->phoneNumberId}/marketing_messages";

        // FIX: Extract campaign_id if present to avoid sending it to Meta
        $campaignId = null;
        if (array_key_exists('campaign_id', $templatePayload)) {
            $campaignId = $templatePayload['campaign_id'];
            unset($templatePayload['campaign_id']);
        }

        // FIX: Auto-process payload to handle large media links
        $templatePayload = $this->preprocessTemplatePayload($templatePayload);

        $templateName = data_get($templatePayload, 'name');
        $templateCost = 0;

        if (is_string($templateName) && $templateName !== '') {
            $templateCost = $this->templateCost($templateName);

            $this->ensurePoints($templateCost, [
                'template' => $templateName,
                'to' => $to,
                'source' => 'marketing_messages',
                'campaign_id' => $campaignId, // Log it here too for validation
            ], $triggerUserId);
        }

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $templatePayload,
        ];

        try {
            $response = Http::withToken($this->apiToken)
                ->retry(3, 500, throw: false)
                ->post($url, $body)
                ->throw();

            $json = $response->json();

            Log::info('[WA-MARKETING] Sent via Marketing API', [
                'phone' => $to,
                'msg_id' => $json['messages'][0]['id'] ?? null,
                'status' => $response->status(),
            ]);

            $wamid = $json['messages'][0]['id'] ?? null;
            if ($templateCost > 0 && $wamid) {
                // Build metadata for points deduction
                $deductionMeta = [
                    'to' => $to,
                    'template' => $templateName,
                    'wamid' => $wamid,
                    'language' => data_get($templatePayload, 'language.code'),
                    'endpoint' => 'marketing_messages',
                ];

                // Append campaign_id if available (FAIL-SAFE Fix)
                if ($campaignId) {
                    $deductionMeta['campaign_id'] = $campaignId;
                }

                $this->points->deductSystemPoints($triggerUserId, $templateCost, 'template_message', $deductionMeta);
            }

            return $json ?? [];

        } catch (RequestException $e) {
            Log::error('[WA-MARKETING] Send failed', [
                'phone' => $to,
                'code' => $e->response?->status(),
                'error' => $e->getMessage(),
                'body' => $e->response?->json() ?? 'No response body',
            ]);

            return null;
        }
    }

    public function sendRichTemplate(
        string $to,
        string $templateName,
        string $locale,
        ?string $mediaUrl = null,
        ?string $mediaType = null, // 'image' or 'video'
        array $params = [], // Renamed from $bodyParams to generic $params
        ?int $triggerUserId = null
    ): ?array {
        $components = [];

        // 1. Handle Media Header
        if ($mediaUrl && $mediaType) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => $mediaType,
                        $mediaType => [
                            'link' => $mediaUrl,
                        ],
                    ],
                ],
            ];
        }

        // 2. Separate Header vs Body params
        // Check if we are receiving the new structured format ['header' => [], 'body' => []]
        $headerParams = [];
        $bodyParams = [];

        if (isset($params['header']) || isset($params['body'])) {
            $headerParams = $params['header'] ?? [];
            $bodyParams = $params['body'] ?? [];
        } else {
            // Fallback: Assume it's a legacy flat array for body only
            $bodyParams = $params;
        }

        // 3. Add Text Header Parameters (if no media is taking the header spot)
        if (! empty($headerParams) && empty($mediaUrl)) {
            $components[] = [
                'type' => 'header',
                'parameters' => array_map(fn ($param) => [
                    'type' => 'text',
                    'text' => (string) $param,
                ], $headerParams),
            ];
        }

        // 4. Add Body Parameters
        if (! empty($bodyParams)) {
            $parameters = array_map(fn ($param) => [
                'type' => 'text',
                'text' => (string) $param,
            ], $bodyParams);

            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        return $this->send($to, [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $locale],
                'components' => $components,
            ],
        ], $triggerUserId);
    }

    private function templateCost(string $templateName): int
    {
        return (int) (MessageTemplate::where('name', $templateName)->value('points_cost') ?? 1);
    }

    private function ensurePoints(int $cost, array $meta = [], ?int $triggerUserId = null): void
    {
        if ($cost <= 0) {
            return;
        }

        if (! $this->points->hasSystemBalance($cost)) {
            Log::warning('[WA] Not enough global points', [
                'cost' => $cost,
                'balance' => $this->points->getSystemBalance(),
                'meta' => $meta,
                'trigger_user_id' => $triggerUserId,
            ]);

            throw new \RuntimeException('Insufficient global points to send WhatsApp template.');
        }
    }

    public function doesTemplateExist(string $name): bool
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return false;
        }

        $accessToken = $this->apiToken;
        $wabaId = config('services.whatsapp.business_account_id');
        $version = $this->graphVersion();

        try {
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", [
                    'name' => $name,
                    'limit' => 50,
                ]);

            $data = $response->json('data') ?? [];

            foreach ($data as $tpl) {
                $remote = strtolower(trim($tpl['name'] ?? ''));
                if ($remote === $name) {
                    return true;
                }
            }

            return false;

        } catch (\Throwable $e) {
            Log::error('[WA] Failed to check template existence', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function createTemplateOnMeta(array $data): array
    {
        $accessToken = $this->apiToken;
        $wabaId = $this->wabaId();
        $version = $this->graphVersion();

        Log::info('[WA-TEMPLATE] createTemplateOnMeta() starting', [
            'version' => $version,
            'waba_id' => $wabaId,
            'phone_number_id' => $this->phoneNumberId,
            'token_present' => filled($accessToken),
            'token_prefix' => filled($accessToken) ? substr($accessToken, 0, 8).'...' : null,
            'template_name' => $data['name'] ?? null,
            'category' => $data['category'] ?? null,
            'language' => $data['language'] ?? null,
        ]);

        $components = [];

        if (! empty($data['header_type']) && $data['header_type'] !== 'NONE') {
            $headerComponent = [
                'type' => 'HEADER',
                'format' => $data['header_type'],
            ];

            if ($data['header_type'] === 'TEXT') {
                $raw = (string) ($data['header_text'] ?? '');
                $clean = $this->sanitizeHeaderTextForMeta($raw);
                $this->validateHeaderTextForMeta($clean);

                $headerComponent['text'] = $clean;

                if (str_contains($headerComponent['text'], '{{1}}') && ! empty($data['header_example'])) {
                    $headerComponent['example'] = [
                        'header_text' => [(string) $data['header_example']],
                    ];
                }
            }

            if (in_array($data['header_type'], ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                $samplePath = $data['header_sample'] ?? null;

                Log::info('[WA-TEMPLATE] header sample debug', [
                    'header_type' => $data['header_type'],
                    'header_sample' => $samplePath,
                ]);

                if ($samplePath) {
                    $mediaId = $this->uploadMediaSampleToMeta($samplePath);
                    $headerComponent['example'] = [
                        'header_handle' => [$mediaId],
                    ];
                }
            }

            $components[] = $headerComponent;
        }

        $bodyText = $data['body_text'] ?? '';
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];

        if (preg_match('/{{\d+}}/', $bodyText)) {
            $repeaterData = $data['body_examples'] ?? [];
            $flatExamples = [];

            foreach ($repeaterData as $row) {
                if (is_array($row)) {
                    $flatExamples[] = $row['value'] ?? '';
                } else {
                    $flatExamples[] = $row;
                }
            }

            if (empty($flatExamples)) {
                throw new \Exception('Meta requires example content for variables in your body text.');
            }

            $bodyComponent['example'] = [
                'body_text' => [$flatExamples],
            ];
        }

        $components[] = $bodyComponent;

        if (! empty($data['footer_text'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $data['footer_text']];
        }

        if (! empty($data['buttons_data'])) {
            $buttons = $this->buildButtonsStrict($data['buttons_data']);
            if (! empty($buttons)) {
                $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
            }
        }

        $payload = [
            'name' => $data['name'],
            'category' => $data['category'],
            'language' => $data['language'],
            'components' => $components,
        ];

        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";

        Log::info('[WA-TEMPLATE] Meta request prepared', [
            'url' => $url,
            'payload_keys' => array_keys($payload),
            'components_count' => count($components),
            'has_header' => (bool) collect($components)->firstWhere('type', 'HEADER'),
            'has_body' => (bool) collect($components)->firstWhere('type', 'BODY'),
        ]);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            Log::info('[WA-TEMPLATE] Meta response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->failed()) {
                $error = $response->json();
                $msg = data_get($error, 'error.message', 'Unknown Meta Error');
                $details = data_get($error, 'error.error_user_msg', '');
                $code = data_get($error, 'error.code');
                $subcode = data_get($error, 'error.error_subcode');

                throw new \Exception("Meta Rejected: {$msg} {$details} (code={$code}, subcode={$subcode})");
            }

            return $response->json() ?? [];

        } catch (\Throwable $e) {
            Log::error('[WA-TEMPLATE] createTemplateOnMeta() failed', [
                'url' => $url,
                'waba_id' => $wabaId,
                'version' => $version,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function syncTemplatesFromMeta(): int
    {
        $templates = $this->listTemplates(null);
        $count = 0;

        foreach ($templates as $tpl) {
            $metaId = $tpl['id'] ?? null;
            $name = $tpl['name'] ?? null;
            if (! $metaId || ! $name) {
                continue;
            }

            $components = $tpl['components'] ?? [];
            $bodyComponent = collect($components)->firstWhere('type', 'BODY');
            $bodyText = $bodyComponent['text'] ?? '';

            MessageTemplate::updateOrCreate(
                ['meta_id' => $metaId],
                [
                    'name' => $name,
                    'category' => $tpl['category'] ?? null,
                    'language' => $tpl['language'] ?? null,
                    'status' => $tpl['status'] ?? null,
                    'components' => $components,
                    'body' => $bodyText,
                    'body_preview' => $bodyText,
                    'local_status' => 'published',
                    'last_synced_at' => now(),
                ]
            );

            $count++;
        }

        Cache::forget('wa:meta_templates:names');

        return $count;
    }

    public function publishTemplateToMeta(MessageTemplate $record): void
    {
        if (filled($record->meta_id)) {
            throw new \RuntimeException('Template is already published on Meta.');
        }

        $data = [
            'name' => $record->name,
            'category' => $record->category,
            'language' => $record->language,
        ];

        $components = $record->components ?? [];
        if (! is_array($components)) {
            $components = [];
        }

        $header = collect($components)->firstWhere('type', 'HEADER');
        $body = collect($components)->firstWhere('type', 'BODY');
        $footer = collect($components)->firstWhere('type', 'FOOTER');
        $buttons = collect($components)->firstWhere('type', 'BUTTONS');

        $data['header_type'] = $header['format'] ?? 'NONE';
        $data['header_text'] = $header['text'] ?? null;

        if (! empty($header['example']['header_text'][0])) {
            $data['header_example'] = $header['example']['header_text'][0];
        }

        $data['header_sample'] = $record->header_sample_path;
        $data['body_text'] = $body['text'] ?? ($record->body ?? '');
        $data['body_examples'] = $body['example']['body_text'][0] ?? [];
        $data['footer_text'] = $footer['text'] ?? null;
        $data['buttons_data'] = $buttons['buttons'] ?? [];

        $json = $this->createTemplateOnMeta($data);

        $metaId = $json['id'] ?? null;
        if (! $metaId) {
            throw new \RuntimeException('Meta did not return a template id.');
        }

        $record->update([
            'meta_id' => $metaId,
            'local_status' => 'published',
            'status' => 'PENDING',
            'rejection_reason' => null,
            'last_synced_at' => now(),
        ]);

        Cache::forget('wa:meta_templates:names');
    }

    public function updateTemplateOnMeta(MessageTemplate $record): void
    {
        if (blank($record->meta_id)) {
            throw new \RuntimeException('Template is not published yet.');
        }

        $payload = $this->buildMetaTemplatePayload($record, isUpdate: true);

        $version = config('services.meta.graph_version', $this->apiVersion ?? 'v24.0');
        $wabaId = config('services.whatsapp.waba_id');

        if (! $wabaId) {
            throw new \RuntimeException('Missing services.whatsapp.waba_id');
        }

        $templateId = $record->meta_id;
        $url = "https://graph.facebook.com/{$version}/{$templateId}";

        $resp = Http::withToken($this->apiToken)
            ->acceptJson()
            ->post($url, $payload);

        $resp->throw();

        $record->update([
            'status' => 'PENDING',
            'rejection_reason' => null,
            'last_synced_at' => now(),
        ]);
    }

    public function refreshTemplateStatus(MessageTemplate $record): void
    {
        if (blank($record->meta_id)) {
            return;
        }

        $templates = $this->listTemplates(null);
        $tpl = collect($templates)->firstWhere('id', $record->meta_id);

        if (! $tpl) {
            $record->update(['last_synced_at' => now()]);

            return;
        }

        $components = $tpl['components'] ?? [];
        $bodyComponent = collect($components)->firstWhere('type', 'BODY');
        $bodyText = $bodyComponent['text'] ?? ($record->body ?? '');

        $record->update([
            'status' => $tpl['status'] ?? $record->status,
            'category' => $tpl['category'] ?? $record->category,
            'language' => $tpl['language'] ?? $record->language,
            'components' => $components ?: $record->components,
            'body' => $bodyText,
            'body_preview' => $bodyText,
            'last_synced_at' => now(),
        ]);
    }

    private function buildMetaTemplatePayload(MessageTemplate $record, bool $isUpdate): array
    {
        $components = $record->components ?? [];
        if (! is_array($components)) {
            $components = [];
        }

        $components = $this->attachHeaderExampleHandleIfNeeded($components, $record);

        $payload = [
            'name' => $record->name,
            'category' => $record->category,
            'language' => $record->language,
            'components' => $components,
        ];

        return $payload;
    }

    private function attachHeaderExampleHandleIfNeeded(array $components, MessageTemplate $record): array
    {
        $headerIndex = null;
        foreach ($components as $i => $c) {
            if (($c['type'] ?? null) === 'HEADER') {
                $headerIndex = $i;
                break;
            }
        }

        if ($headerIndex === null) {
            return $components;
        }

        $format = $components[$headerIndex]['format'] ?? null;
        if (! in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return $components;
        }

        $samplePath = $record->header_sample_path;

        if (! $samplePath || ! is_string($samplePath)) {
            return $components;
        }

        $mediaId = $this->uploadMediaSampleToMeta($samplePath);

        $components[$headerIndex]['example'] = [
            'header_handle' => [$mediaId],
        ];

        return $components;
    }

    private function uploadMediaSampleToMeta(string $publicDiskPath): string
    {
        $appId = config('services.meta.app_id');
        if (! $appId) {
            throw new \RuntimeException('Missing services.meta.app_id. Please add META_APP_ID to your .env file.');
        }

        $version = config('services.meta.graph_version', $this->apiVersion ?? 'v21.0');
        $accessToken = $this->apiToken;

        $fullPath = storage_path('app/public/'.ltrim($publicDiskPath, '/'));
        if (! file_exists($fullPath)) {
            throw new \RuntimeException("Header sample file not found: {$publicDiskPath}");
        }

        // --- GLOBAL COMPRESSOR CHECK ---
        $fileSize = filesize($fullPath);
        $compressedPath = null;
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        $LIMIT_VIDEO_16MB = 16 * 1024 * 1024;
        $LIMIT_IMAGE_5MB = 5 * 1024 * 1024;

        // 1. Video Compression
        if (str_starts_with($mime, 'video/') && $fileSize > $LIMIT_VIDEO_16MB) {
            Log::info("[WA] File {$publicDiskPath} is {$fileSize} bytes. Attempting VIDEO compression...");

            try {
                $compressedPath = $this->compressor->compress($fullPath);

                if ($compressedPath && file_exists($compressedPath)) {
                    $fullPath = $compressedPath;
                    $fileSize = filesize($fullPath);
                    Log::info("[WA] Video compression successful. New size: {$fileSize} bytes.");
                }
            } catch (\Exception $e) {
                Log::warning('[WA] Auto-compression failed: '.$e->getMessage());
            }
        }
        // 2. Image Compression
        elseif (str_starts_with($mime, 'image/') && $fileSize > $LIMIT_IMAGE_5MB) {
            Log::info("[WA] File {$publicDiskPath} is {$fileSize} bytes. Attempting IMAGE compression...");

            try {
                $compressedPath = $this->compressor->compressImage($fullPath);

                if ($compressedPath && file_exists($compressedPath)) {
                    $fullPath = $compressedPath;
                    $fileSize = filesize($fullPath);
                    Log::info("[WA] Image compression successful. New size: {$fileSize} bytes.");
                }
            } catch (\Exception $e) {
                Log::warning('[WA] Image compression failed: '.$e->getMessage());
            }
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        // 3. STEP A: Create an Upload Session
        $urlSession = "https://graph.facebook.com/{$version}/{$appId}/uploads";

        Log::info('[WA-UPLOAD] uploadMediaSampleToMeta: Starting upload session', [
            'url' => $urlSession,
            'file_length' => $fileSize,
            'file_type' => $mime,
        ]);

        try {
            $responseSession = Http::withToken($accessToken)
                ->post($urlSession, [
                    'file_length' => $fileSize,
                    'file_type' => $mime,
                ]);

            $responseSession->throw();

            $uploadSessionId = $responseSession->json('id');

            if (! $uploadSessionId) {
                Log::error('[WA-UPLOAD] uploadMediaSampleToMeta: Failed to get session ID', ['response' => $responseSession->body()]);
                throw new \RuntimeException('Failed to create Meta upload session.');
            }

            Log::info('[WA-UPLOAD] uploadMediaSampleToMeta: Session created', ['session_id' => $uploadSessionId]);

            // 4. STEP B: Upload the Binary Content
            $urlUpload = "https://graph.facebook.com/{$version}/{$uploadSessionId}";

            $handle = fopen($fullPath, 'r');
            $contents = stream_get_contents($handle);
            fclose($handle);

            $responseUpload = Http::withHeaders([
                'Authorization' => 'OAuth '.$accessToken,
                'file_offset' => 0,
            ])
                ->withBody($contents, $mime)
                ->post($urlUpload);

            $responseUpload->throw();

            // 5. Return the Handle ('h')
            $handleString = $responseUpload->json('h');

            if (! $handleString) {
                Log::error('[WA-UPLOAD] uploadMediaSampleToMeta: Failed to get handle', ['response' => $responseUpload->body()]);
                throw new \RuntimeException('Meta upload failed: No handle (h) returned.');
            }

            Log::info('[WA-UPLOAD] uploadMediaSampleToMeta: Upload successful', ['handle' => $handleString]);

        } finally {
            if ($compressedPath && file_exists($compressedPath)) {
                @unlink($compressedPath);
            }
        }

        return $handleString;
    }

    /**
     * Inspects template components. If it finds a Header with a Link,
     * it attempts to compress and upload it to Meta, swapping 'link' for 'id'.
     * Caches the Media ID for 24 hours to avoid re-uploading for every recipient.
     */
    private function preprocessTemplatePayload(array $template): array
    {
        if (empty($template['components'])) {
            return $template;
        }

        foreach ($template['components'] as &$component) {
            if (($component['type'] ?? '') !== 'header') {
                continue;
            }

            foreach ($component['parameters'] ?? [] as &$param) {
                $type = $param['type'] ?? null;

                // Only check Video or Image
                if (! in_array($type, ['video', 'image'])) {
                    continue;
                }

                $link = $param[$type]['link'] ?? null;

                // If it's already an ID, ignore. If no link, ignore.
                if (empty($link)) {
                    continue;
                }

                // CACHE CHECK: Have we already uploaded this URL?
                $cacheKey = 'wa_opt_media_'.sha1($link);
                $cachedId = Cache::get($cacheKey);

                if ($cachedId) {
                    // Swap link for ID
                    unset($param[$type]['link']);
                    $param[$type]['id'] = $cachedId;

                    continue;
                }

                // PROCESS: Download -> Compress -> Upload -> Cache
                try {
                    Log::info("[WA-AUTO-COMPRESS] Processing media link: $link");

                    // 1. Download to Temp (Fix extension)
                    $ext = pathinfo(parse_url($link, PHP_URL_PATH), PATHINFO_EXTENSION);
                    if (! $ext) {
                        $ext = ($type === 'video' ? 'mp4' : 'jpg');
                    }
                    $tempPath = sys_get_temp_dir().'/wa_down_'.uniqid().'.'.$ext;

                    // FIX: Use sink() to stream download, avoiding memory exhaustion on large files
                    $response = Http::timeout(120)->sink($tempPath)->get($link);

                    if ($response->failed()) {
                        Log::error("[WA-AUTO-COMPRESS] Failed to download media (HTTP {$response->status()}), skipping.");

                        continue;
                    }

                    $finalPath = $tempPath;
                    $isCompressed = false;
                    $mime = mime_content_type($finalPath);
                    $size = filesize($finalPath);

                    // Limits
                    $LIMIT_VIDEO_16MB = 16 * 1024 * 1024;
                    $LIMIT_IMAGE_5MB = 5 * 1024 * 1024;

                    if ($type === 'video' && $size > $LIMIT_VIDEO_16MB) {
                        try {
                            $finalPath = $this->compressor->compress($tempPath); // Uses Global Service
                            $isCompressed = true;
                        } catch (\Exception $e) {
                            Log::error('[WA-AUTO-COMPRESS] Video compression failed: '.$e->getMessage());
                        }
                    } elseif ($type === 'image' && $size > $LIMIT_IMAGE_5MB) {
                        try {
                            $finalPath = $this->compressor->compressImage($tempPath); // Uses Global Service
                            $isCompressed = true;
                        } catch (\Exception $e) {
                            Log::error('[WA-AUTO-COMPRESS] Image compression failed: '.$e->getMessage());
                        }
                    }

                    // 3. Upload to Meta (Get ID)
                    // We need a simple media upload to get an ID that works for sending messages.
                    $mediaId = $this->uploadForSending($finalPath, $mime);

                    if ($mediaId) {
                        // Success! Cache and Swap
                        Cache::put($cacheKey, $mediaId, now()->addHours(24)); // Cache for 24h

                        unset($param[$type]['link']);
                        $param[$type]['id'] = $mediaId;

                        Log::info("[WA-AUTO-COMPRESS] Swapped link for Media ID: $mediaId");
                    } else {
                        Log::warning('[WA-AUTO-COMPRESS] Upload failed, reverting to link.');
                    }

                    // Cleanup
                    @unlink($tempPath);
                    if ($isCompressed && $finalPath !== $tempPath) {
                        @unlink($finalPath);
                    }

                } catch (\Throwable $e) {
                    Log::error('[WA-AUTO-COMPRESS] General failure: '.$e->getMessage());
                    // On error, we leave the 'link' as is and hope Meta accepts it
                }
            }
        }

        return $template;
    }

    /**
     * Helper to upload a file for sending messages (Messaging API).
     * Uses Resumable upload for reliability with large files.
     */
    private function uploadForSending(string $filePath, string $mime): ?string
    {
        // 1. Get Configuration
        $appId = config('services.meta.app_id');
        if (! $appId) {
            Log::error('[WA-UPLOAD] uploadForSending: Missing META_APP_ID');

            return null;
        }

        $version = config('services.meta.graph_version', $this->apiVersion ?? 'v21.0');
        $accessToken = $this->apiToken;
        $fileSize = filesize($filePath);

        // 2. Start Upload Session
        $urlSession = "https://graph.facebook.com/{$version}/{$appId}/uploads";

        Log::info('[WA-UPLOAD] uploadForSending: Starting upload session', [
            'url' => $urlSession,
            'file_length' => $fileSize,
            'file_type' => $mime,
        ]);

        try {
            $responseSession = Http::withToken($accessToken)
                ->post($urlSession, [
                    'file_length' => $fileSize,
                    'file_type' => $mime,
                ]);

            if ($responseSession->failed()) {
                Log::error('[WA-UPLOAD] uploadForSending: Failed to create session', ['response' => $responseSession->body()]);

                return null;
            }

            $uploadSessionId = $responseSession->json('id');

            if (! $uploadSessionId) {
                Log::error('[WA-UPLOAD] uploadForSending: No session ID returned', ['response' => $responseSession->body()]);

                return null;
            }

            Log::info('[WA-UPLOAD] uploadForSending: Session created', ['session_id' => $uploadSessionId]);

            // 3. Upload Content
            $urlUpload = "https://graph.facebook.com/{$version}/{$uploadSessionId}";

            $handle = fopen($filePath, 'r');
            $contents = stream_get_contents($handle);
            fclose($handle);

            $responseUpload = Http::withHeaders([
                'Authorization' => 'OAuth '.$accessToken,
                'file_offset' => 0,
            ])
                ->withBody($contents, $mime)
                ->post($urlUpload);

            if ($responseUpload->failed()) {
                Log::error('[WA-UPLOAD] uploadForSending: Failed to upload content', ['response' => $responseUpload->body()]);

                return null;
            }

            // 4. Return Handle
            $handleString = $responseUpload->json('h'); // 'h' is the handle/ID for sending

            if (! $handleString) {
                Log::error('[WA-UPLOAD] uploadForSending: No handle returned', ['response' => $responseUpload->body()]);

                return null;
            }

            Log::info('[WA-UPLOAD] uploadForSending: Upload successful', ['handle' => $handleString]);

            return $handleString;

        } catch (\Throwable $e) {
            Log::error('[WA-UPLOAD] Upload failed: '.$e->getMessage());

            return null;
        }
    }

    private function sanitizeHeaderTextForMeta(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text); // no newlines
        $text = preg_replace('/\s+/', ' ', $text);            // collapse spaces

        // remove WhatsApp formatting markers that Meta rejects in header
        $text = str_replace(['*', '_', '~', '`'], '', $text);

        // remove emojis (best-effort)
        $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $text);

        return trim($text);
    }

    private function validateHeaderTextForMeta(string $text): void
    {
        if (preg_match("/[\r\n]/", $text)) {
            throw new \RuntimeException('Header text cannot contain new lines.');
        }

        if (preg_match('/[*_~`]/', $text)) {
            throw new \RuntimeException('Header text cannot contain formatting characters (* _ ~ `).');
        }

        if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text)) {
            throw new \RuntimeException('Header text cannot contain emojis.');
        }
    }
}
