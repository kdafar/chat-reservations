<?php

namespace App\Wa\Http\Controllers\Api;

use App\Wa\Helpers\WhatsAppCrypto;
use App\Wa\Http\Controllers\Controller;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Models\PointRefund;
use App\Wa\Models\PointUsage;
use App\Wa\Models\WhatsApp\WaContact;
use App\Wa\Models\WhatsApp\WaConversation;
use App\Wa\Models\WhatsApp\WaMessage;
use App\Wa\Models\WhatsApp\WaNumber;
use App\Wa\Services\GlobalPointService;
use App\Wa\Services\WhatsApp\WhatsAppFlowService;
use App\Wa\Services\WhatsApp\WhatsAppMessageHandler;
use App\Wa\Traits\LogCustomize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectBotController extends Controller
{
    use LogCustomize;

    private WhatsAppMessageHandler $messageHandler;

    private WhatsAppFlowService $flowService;

    private GlobalPointService $pointService;

    public function __construct(
        WhatsAppMessageHandler $messageHandler,
        WhatsAppFlowService $flowService,
        GlobalPointService $pointService
    ) {
        // Set the logger depth first
        self::setMaxLoggerNormalizeDepth(Log::getLogger(), 50);

        // Then assign the services
        $this->messageHandler = $messageHandler;
        $this->flowService = $flowService;
        $this->pointService = $pointService;
    }

    public function verifyWebhook(Request $request): JsonResponse|Response
    {
        $verifyToken = config('services.whatsapp.webhook_secret');
        if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === $verifyToken) {
            return response($request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Invalid verification token or mode.', 403);
    }

    private function forwardToChatwoot(array $payload): void
    {
        $url = config('chatwoot.whatsapp_webhook_url');

        // Use a dedicated log channel 'chatwoot' if configured, otherwise falls back to default
        $logger = Log::channel('chatwoot');

        if (! $url) {
            $logger->warning('[WA→Chatwoot] No CHATWOOT_WEBHOOK_URL configured.');

            return;
        }

        try {
            // Send the *same* body WhatsApp sent to us
            Http::asJson()->post($url, $payload);

            $logger->info('[WA→Chatwoot] Forwarded payload to Chatwoot WA webhook', [
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            $logger->error('[WA→Chatwoot] Forward failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $value = data_get($payload, 'entry.0.changes.0.value', []);

        // 1) STATUS UPDATES
        foreach ($value['statuses'] ?? [] as $statusPayload) {
            $this->handleStatusUpdate($statusPayload);
        }

        // 2) MESSAGES
        $messagePayload = $value['messages'][0] ?? null;
        if ($messagePayload) {
            $this->handleIncomingMessage($value, $messagePayload);
        }

        // 3) Forward everything to Chatwoot’s WA webhook so it also sees it
        $this->forwardToChatwoot($payload);

        return response()->json(['status' => 'processed']);
    }

    /**
     * Handles outgoing message status updates (sent, delivered, read, failed).
     * 1) Updates WaMessage (Inbox)
     * 2) Updates PromotionalCampaignRecipient (Bulk campaigns)
     */
    private function handleStatusUpdate(array $statusPayload): void
    {
        $wamid = $statusPayload['id'] ?? null;
        $status = $statusPayload['status'] ?? null;

        $errorCode = data_get($statusPayload, 'errors.0.code');
        $errorTitle = data_get($statusPayload, 'errors.0.title');
        $errorDetail = data_get($statusPayload, 'errors.0.error_data.details');

        $conversationId = data_get($statusPayload, 'conversation.id');
        $pricingModel = data_get($statusPayload, 'pricing.pricing_model');

        Log::info('[WA] Status', [
            'wamid' => $wamid,
            'to' => $statusPayload['recipient_id'] ?? null,
            'status' => $status,
            'code' => $errorCode,
            'title' => $errorTitle,
            'conversation_id' => $conversationId,
            'pricing_model' => $pricingModel,
        ]);

        // ------------------------------------------------------------------
        // 1) INBOX: update WaMessage row if we have it
        // ------------------------------------------------------------------
        if ($wamid && $status) {
            $message = WaMessage::where('meta_message_id', $wamid)->first();

            if ($message) {
                $message->status = $status; // sent, delivered, read, failed...

                if ($status === 'failed') {
                    $raw = $message->meta_raw ?? [];
                    if (! is_array($raw)) {
                        $raw = [];
                    }

                    $raw['error'] = $statusPayload['errors'] ?? null;
                    $message->meta_raw = $raw;
                }

                $message->save();
            }
        }

        // ------------------------------------------------------------------
        // 2) BULK CAMPAIGNS: update PromotionalCampaignRecipient
        //    We match by wa_message_id that we set in SendPromotionalCampaignMessage
        // ------------------------------------------------------------------
        if ($wamid && $status) {
            $recipient = PromotionalCampaignRecipient::where('wa_message_id', $wamid)->first();

            if ($recipient) {
                // Map Meta status → our internal status + timestamps
                switch ($status) {
                    case 'sent':
                        $recipient->status = 'sent';
                        if (! $recipient->sent_at) {
                            $recipient->sent_at = now();
                        }
                        break;

                    case 'delivered':
                        $recipient->status = 'delivered';
                        $recipient->delivered_at = now();
                        if (! $recipient->sent_at) {
                            $recipient->sent_at = now();
                        }
                        break;

                    case 'read':
                        $recipient->status = 'read';
                        $recipient->read_at = now();
                        if (! $recipient->sent_at) {
                            $recipient->sent_at = now();
                        }
                        break;

                    case 'failed':
                        // Always keep raw meta status payload for debugging
                        $recipient->wa_status_payload = $statusPayload;
                        $recipient->wa_error_code = $errorCode ? (string) $errorCode : null;
                        $recipient->wa_error_title = $errorTitle ?: null;

                        switch ((string) $errorCode) {
                            // Ecosystem health / marketing limit
                            case '131049':
                                $recipient->status = 'limited';
                                $recipient->error_message = $errorDetail
                                    ?: 'Meta limited delivery for this user due to marketing frequency (ecosystem health).';
                                break;

                                // Undeliverable: unregistered WA, old client, TOS not accepted, etc.
                            case '131026':
                                $recipient->status = 'undeliverable';
                                $recipient->error_message = $errorDetail
                                    ?: 'Message undeliverable (unregistered number / outdated client / TOS not accepted).';
                                break;

                                // Marketing Message Experiment – not included in experiment
                            case '130472':
                                $recipient->status = 'experiment_blocked';
                                $recipient->error_message = $errorDetail
                                    ?: 'Message skipped by Meta marketing experiment (not part of experiment).';
                                break;

                            default:
                                $recipient->status = 'failed';
                                $recipient->error_message = $errorDetail
                                    ?: ($errorTitle ?: 'Message failed (no details)');
                                break;
                        }

                        // --- POINT REFUND LOGIC START ---
                        $this->refundPointsIfApplicable($recipient);
                        // --- POINT REFUND LOGIC END ---

                        break;

                    default:
                        // unknown status → just store raw value
                        $recipient->status = $status;
                        break;
                }

                // Always store raw payload + meta from status
                $recipient->wa_status_payload = $statusPayload;
                if ($conversationId) {
                    $recipient->wa_conversation_id = $conversationId;
                }
                if ($pricingModel) {
                    $recipient->wa_pricing_model = $pricingModel;
                }

                $recipient->save();

                Log::info('[PromoCampaign] Updated recipient from webhook', [
                    'recipient_id' => $recipient->id,
                    'campaign_id' => $recipient->promotional_campaign_id ?? null,
                    'status' => $recipient->status,
                ]);

                // Optional: refresh campaign counters
                $campaign = $recipient->campaign;   // BelongsTo relation
                if ($campaign && method_exists($campaign, 'updateCounts')) {
                    $campaign->updateCounts();
                }
            }
        }

        // ------------------------------------------------------------------
        // 3) OLD EVENT LOGIC (KEEP)
        // ------------------------------------------------------------------
        event(new \App\Events\OutgoingWhatsappStatusReceived($statusPayload));
    }

    /**
     * Refounds points for a failed/undeliverable message if not already refunded.
     */
    private function refundPointsIfApplicable(PromotionalCampaignRecipient $recipient): void
    {
        // 1. Ensure we have a WAMID to look up
        if (! $recipient->wa_message_id) {
            return;
        }

        // 2. Find the original point usage record
        // We look for the record that charged for this specific message ID
        $usage = PointUsage::query()
            ->where('meta->wamid', $recipient->wa_message_id)
            ->first();

        // If usage record is gone, it's already refunded (or never charged)
        if (! $usage) {
            return;
        }

        // 3. Create Audit Record (Sanity Check)
        // We save this BEFORE deleting so we don't lose the data
        PointRefund::create([
            'user_id' => $usage->user_id,
            'points' => $usage->points, // The amount we are refunding
            'reason' => $recipient->status, // failed, undeliverable, etc.
            'wamid' => $recipient->wa_message_id,
            'campaign_id' => $recipient->promotional_campaign_id,
            'original_meta' => $usage->meta,
            'refunded_at' => now(),
        ]);

        // 4. Delete the PointUsage Record
        // This removes it from the SUM calculation, effectively refunding the points.
        $usage->delete();

        Log::info("[PromoCampaign] Refunded (deleted) usage record for failed recipient #{$recipient->id}");
    }

    /**
     * Handles incoming messages.
     * 1. Saves to WaConversation/WaMessage for the Inbox.
     * 2. Passes to WhatsappSession/MessageHandler for the Bot.
     */
    private function handleIncomingMessage(array $value, array $messagePayload): void
    {
        $contactPayload = $value['contacts'][0] ?? null;
        $from = $messagePayload['from'] ?? null; // customer WA id (digits)

        if (! $from) {
            Log::error('[WA Webhook] Missing message "from".', $messagePayload);

            return;
        }

        if (! $contactPayload) {
            Log::error('[WA Webhook] No contact payload found for incoming message.', $messagePayload);

            return;
        }

        // normalize phone (digits only) and standardize storage format to +<digits>
        $normalizedFrom = $this->normalizePhone($from);
        $sessionPhone = $normalizedFrom ? ('+'.$normalizedFrom) : $from;

        // ---------------------------------------------------------------------
        // 1) INBOX LOGIC (Save to WaConversation/WaMessage) - unchanged behavior
        // ---------------------------------------------------------------------
        $waNumber = WaNumber::where('phone_number_id', data_get($value, 'metadata.phone_number_id'))->first();
        if (! $waNumber) {
            Log::error('[WA Webhook] Message for unknown phone_number_id: '.data_get($value, 'metadata.phone_number_id'));
            // do not return; bot logic should still run
        }

        if ($waNumber) {
            $customerName = data_get($contactPayload, 'profile.name') ?: 'Unknown';

            // Find or Create the customer (WaContact)
            $waContact = WaContact::updateOrCreate(
                ['wa_id' => $from],
                [
                    'wa_account_id' => $waNumber->wa_account_id,
                    'name' => $customerName,
                    'phone' => $sessionPhone, // store standardized phone too
                ]
            );

            // Find or Create conversation
            $conversation = WaConversation::findOrCreate($waNumber, $waContact);

            // Store message
            $messageType = $messagePayload['type'] ?? 'unknown';
            $body = $this->extractMessageBody($messagePayload);

            WaMessage::create([
                'wa_account_id' => $waNumber->wa_account_id,
                'wa_number_id' => $waNumber->id,
                'contact_id' => $waContact->id,
                'conversation_id' => $conversation->id,
                'meta_message_id' => $messagePayload['id'] ?? null,
                'direction' => 'in',
                'type' => $messageType,
                'body' => $body,
                'meta_raw' => $messagePayload,
                'status' => 'delivered',
                'sent_at' => now(),
            ]);
        }

        // ---------------------------------------------------------------------
        // 2) BOT SESSION LOGIC (FIXED: canonical-first + no unique collisions)
        // ---------------------------------------------------------------------
        $customerName = data_get($contactPayload, 'profile.name');

        $session = null;

        // 2a) Prefer canonical +<digits> session FIRST (prevents using 965... row)
        if ($normalizedFrom) {
            $session = WhatsappSession::query()
                ->where('customer_phone_number', '+'.$normalizedFrom)
                ->latest('updated_at')
                ->first();
        }

        // 2b) Fallback to legacy formats (raw $from or digits-only)
        if (! $session) {
            $session = WhatsappSession::query()
                ->where(function ($q) use ($from, $normalizedFrom) {
                    $q->where('customer_phone_number', $from);

                    if ($normalizedFrom) {
                        $q->orWhere('customer_phone_number', $normalizedFrom);
                    }
                })
                ->latest('updated_at')
                ->first();
        }

        // 2c) Create if missing
        if (! $session) {
            try {
                $session = WhatsappSession::create([
                    'customer_phone_number' => $sessionPhone, // store canonical when possible
                    'customer_name' => $customerName ?: 'Unknown',
                    'status' => 'active',
                    'locale' => 'en',
                    'last_interacted_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Rare race: someone else created canonical session concurrently
                $session = WhatsappSession::query()
                    ->where('customer_phone_number', $sessionPhone)
                    ->latest('updated_at')
                    ->first();

                if (! $session) {
                    // If still missing, rethrow (something else is wrong)
                    throw $e;
                }
            }
        } else {
            // 2d) Update safely (never update phone into collision)
            $update = [
                'last_interacted_at' => now(),
            ];

            // Update name only if empty (don’t overwrite manual edits)
            if (blank($session->customer_name) && ! blank($customerName)) {
                $update['customer_name'] = $customerName;
            }

            // If current row is NOT canonical but we have a canonical phone, attempt to normalize:
            // - If canonical row exists -> switch to it and update only that row
            // - Else -> safely set phone on current row
            if ($normalizedFrom && $session->customer_phone_number !== $sessionPhone) {

                $canonical = WhatsappSession::query()
                    ->where('customer_phone_number', $sessionPhone) // +<digits>
                    ->where('id', '!=', $session->id)
                    ->latest('updated_at')
                    ->first();

                if ($canonical) {
                    // Switch to canonical row
                    $session = $canonical;

                    // Rebuild update payload for canonical row
                    $update = [
                        'last_interacted_at' => now(),
                    ];

                    if (blank($session->customer_name) && ! blank($customerName)) {
                        $update['customer_name'] = $customerName;
                    }

                    // IMPORTANT: do NOT set customer_phone_number here
                } else {
                    // Safe: no collision with unique constraint
                    $update['customer_phone_number'] = $sessionPhone;
                }
            }

            $session->update($update);
        }

        // ---------------------------------------------------------------------
        // 3) STOP/START only (does NOT block normal bot usage)
        // ---------------------------------------------------------------------
        if (! $this->applyStopStartGuard($session, $messagePayload)) {
            return;
        }

        event(new \App\Events\IncomingWhatsappMessageReceived($session, $messagePayload));
        $this->messageHandler->handle($messagePayload);
    }

    /**
     * NEW: Helper to extract text from various message types
     */
    private function extractMessageBody(array $messagePayload): ?string
    {
        $messageType = $messagePayload['type'] ?? 'unknown';
        switch ($messageType) {
            case 'text':
                return $messagePayload['text']['body'] ?? null;
            case 'interactive':
                $interactive = $messagePayload['interactive'] ?? [];
                if (isset($interactive['button_reply'])) {
                    return $interactive['button_reply']['title'] ?? 'Button Click';
                } elseif (isset($interactive['list_reply'])) {
                    return $interactive['list_reply']['title'] ?? 'List Selection';
                }

                return 'Interactive Message';
            case 'button':
                return $messagePayload['button']['text'] ?? 'Button Click';
            case 'reaction':
                return 'Reacted: '.($messagePayload['reaction']['emoji'] ?? 'unknown');
            case 'image':
                return $messagePayload['image']['caption'] ?? '[Image]';
            case 'document':
                return $messagePayload['document']['caption'] ?? '[Document]';
            default:
                return "[$messageType message]";
        }
    }

    // ========================================================================
    // Your Data Exchange (Flow) functions - Unmodified
    // This code will continue to use the "old" WhatsappSession model
    // ========================================================================

    public function handleDataExchange(Request $request): Response
    {
        $incoming = $request->all();
        $decrypted = WhatsAppCrypto::decrypt($incoming);   // null on failure / health ping

        $aesKey = $decrypted['_wa_aes_key'] ?? $incoming['_wa_aes_key'] ?? str_repeat('0', 32);
        $ivB64 = $decrypted['_wa_iv_b64'] ?? $incoming['_wa_iv_b64'] ?? base64_encode(str_repeat("\0", 16));

        $payload = $decrypted ?? [];
        $isEncrypted = $decrypted !== null;

        // ── 0) Handle health pings cleanly ───────────────────────────────────────
        $action = $payload['action'] ?? $payload['payload']['action'] ?? null;
        if ($action === 'ping') {
            Log::debug('[WA] Health ping');

            $ok = ['data' => ['status' => 'active']];
            $encrypted = WhatsAppCrypto::encrypt(
                json_encode($ok, JSON_UNESCAPED_UNICODE),
                $aesKey,
                $ivB64
            );

            return response($encrypted, 200, ['Content-Type' => 'text/plain']);
        }

        // ── 1) Resolve session (FIXED: canonical phone + avoid unique collisions) ──
        $session = null;
        $locale = $payload['locale'] ?? 'en';
        $flowToken = $payload['flow_token'] ?? null;

        // Phones from payload (raw + canonical)
        $phoneRaw = data_get($payload, 'data.customer_phone') ?? data_get($payload, 'customer_phone');
        $phoneCanonical = $this->canonicalSessionPhone($phoneRaw); // returns +<digits> or null

        if ($flowToken) {
            /**
             * IMPORTANT:
             * customer_phone_number is UNIQUE in DB.
             * firstOrCreate(['flow_token'=>...]) may try to INSERT a new row with phone that already exists,
             * causing a duplicate key crash.
             *
             * So we must prefer resolving by phone first, then flow_token.
             */

            // 1A) Prefer existing session by canonical phone (if available)
            if ($phoneCanonical) {
                $session = WhatsappSession::query()
                    ->where('customer_phone_number', $phoneCanonical)
                    ->latest('updated_at')
                    ->first();
            }

            // 1B) If found by phone, optionally attach flow_token (only if it won't collide)
            if ($session) {
                $update = [
                    'last_interacted_at' => now(),
                ];

                // keep locale stable: only set if blank
                if (blank($session->locale) && ! blank($locale)) {
                    $update['locale'] = $locale;
                }

                // Attach flow_token if missing and not used elsewhere
                if (blank($session->flow_token)) {
                    $tokenTaken = WhatsappSession::query()
                        ->where('flow_token', $flowToken)
                        ->where('id', '!=', $session->id)
                        ->exists();

                    if (! $tokenTaken) {
                        $update['flow_token'] = $flowToken;
                    }
                }

                if (! empty($update)) {
                    $session->update($update);
                }
            }

            // 1C) If not found by phone, resolve/create by flow_token
            if (! $session) {
                $createPhone = $phoneCanonical ?? $phoneRaw;

                try {
                    $session = WhatsappSession::firstOrCreate(
                        ['flow_token' => $flowToken],
                        [
                            'customer_phone_number' => $createPhone,
                            'status' => 'active',
                            'locale' => $locale,
                            'last_interacted_at' => now(),
                        ]
                    );
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Race or existing session by phone: fall back to canonical phone session
                    if ($phoneCanonical) {
                        $session = WhatsappSession::query()
                            ->where('customer_phone_number', $phoneCanonical)
                            ->latest('updated_at')
                            ->first();
                    }

                    // If still missing, rethrow (unexpected)
                    if (! $session) {
                        throw $e;
                    }

                    // Touch it
                    $session->update(['last_interacted_at' => now()]);
                }
            }
        } else {
            // Fallback: resolve by phone only (canonicalized)
            $fallbackPhone = $phoneCanonical ?? $phoneRaw;

            if ($fallbackPhone) {
                try {
                    $session = WhatsappSession::firstOrCreate(
                        ['customer_phone_number' => $fallbackPhone],
                        [
                            'status' => 'active',
                            'locale' => $locale,
                            'last_interacted_at' => now(),
                        ]
                    );
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Rare race: someone else created it concurrently
                    $session = WhatsappSession::query()
                        ->where('customer_phone_number', $fallbackPhone)
                        ->latest('updated_at')
                        ->first();

                    if (! $session) {
                        throw $e;
                    }

                    $session->update(['last_interacted_at' => now()]);
                }

                Log::info('[WA] Resolved session by phone (no flow_token)', ['phone' => $fallbackPhone]);
            } else {
                Log::info('[WA] No flow_token and no phone – proceeding stateless for this call');
            }
        }

        // ── 2) Delegate to service ───────────────────────────────────────────────
        $responsePayload = $this->flowService->buildFlowResponse($payload, $session, $locale);
        Log::debug('[WA] Service response', $responsePayload);

        // ── 3) Encrypt & return ──────────────────────────────────────────────────
        $encrypted = WhatsAppCrypto::encrypt(
            json_encode($responsePayload, JSON_UNESCAPED_UNICODE),
            $aesKey,
            $ivB64
        );

        return response($encrypted, 200, ['Content-Type' => 'text/plain']);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // digits only
        $digits = preg_replace('/\D+/', '', $phone);

        return $digits ?: null;
    }

    private function extractCommandText(array $messagePayload): string
    {
        $type = $messagePayload['type'] ?? 'text';

        if ($type === 'text') {
            return trim($messagePayload['text']['body'] ?? '');
        }

        if ($type === 'interactive') {
            return (string) (
                data_get($messagePayload, 'interactive.button_reply.title')
                ?? data_get($messagePayload, 'interactive.list_reply.title')
                ?? data_get($messagePayload, 'interactive.button_reply.id')
                ?? data_get($messagePayload, 'interactive.list_reply.id')
                ?? ''
            );
        }

        return '';
    }

    private function applyStopStartGuard(WhatsappSession $session, array $messagePayload): bool
    {
        $customerPhone = $session->customer_phone_number;
        $bodyRaw = $this->extractCommandText($messagePayload);
        $lowBody = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', '', trim($bodyRaw)));

        // STOP (opt-out marketing only)
        $stopKeywords = ['stop', 'unsubscribe', 'cancel', 'إيقاف', 'الغاء', 'إلغاء', 'خروج', 'قف'];
        if ($lowBody !== '' && in_array($lowBody, $stopKeywords, true)) {
            $session->update([
                'is_blocked' => true,
                'last_interacted_at' => now(),
            ]);

            $msg = $session->locale === 'ar'
                ? 'تم إلغاء الاشتراك في الرسائل الترويجية. ما زالت الخدمة تعمل للطلبات والدعم. لإعادة التفعيل أرسل *تفعيل*.'
                : 'You have opted out of promotional messages. Ordering/support still works. Reply *START* to resubscribe.';

            app(\App\Services\WhatsApp\WhatsAppService::class)->sendTextMessage($customerPhone, $msg);

            return false;
        }

        // START (re-enable marketing)
        $startKeywords = ['start', 'resubscribe', 'unstop', 'تفعيل', 'بدء', 'اشتراك'];
        if ($lowBody !== '' && in_array($lowBody, $startKeywords, true)) {
            $session->update([
                'is_blocked' => false,
                'last_interacted_at' => now(),
            ]);

            $msg = $session->locale === 'ar'
                ? 'تم تفعيل استقبال الرسائل الترويجية مرة أخرى '
                : 'You will receive promotional messages again ';

            app(\App\Services\WhatsApp\WhatsAppService::class)->sendTextMessage($customerPhone, $msg);

            return false;
        }

        // IMPORTANT: do NOT block normal bot usage
        return true;
    }

    private function canonicalSessionPhone(?string $phone): ?string
    {
        $digits = $this->normalizePhone($phone);

        return $digits ? ('+'.$digits) : null;
    }
}
