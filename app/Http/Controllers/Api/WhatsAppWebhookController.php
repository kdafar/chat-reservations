<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WAMessageLog;
use App\Services\BookingFlowService;
use App\Services\WhatsAppApiServiceFactory;
use App\Services\WhatsAppSender;
use App\Support\Settings as Sys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, BookingFlowService $bookingFlow)
    {
        $rid = (string) Str::uuid();

        $ctx = [
            'rid' => $rid,
            'method' => $request->method(),
            'ip' => $request->ip(),
        ];

        Log::info('WA webhook: request start', $ctx);

        try {
            // Tokens from admin settings first, then config fallback.
            $verifyToken = (string) (Sys::get('whatsapp.verify_token') ?? config('services.whatsapp.verify_token', ''));
            $appSecret = (string) (Sys::get('whatsapp.app_secret') ?? config('services.whatsapp.app_secret', ''));

            // === GET: Meta verification handshake
            //
            // The Netflie SDK's VerificationRequest::validate() echoes the
            // challenge back even on a wrong token (it only calls
            // http_response_code(403), which Laravel discards). That would
            // let any caller successfully complete Meta's handshake against
            // our endpoint — so we validate the token ourselves first and
            // only fall through to the SDK when it actually matches.
            if ($request->isMethod('get')) {
                $mode = (string) $request->query('hub_mode', '');
                $token = (string) $request->query('hub_verify_token', '');
                $challenge = (string) $request->query('hub_challenge', '');

                if ($mode !== 'subscribe' || $verifyToken === '' || ! hash_equals($verifyToken, $token)) {
                    Log::warning('WA webhook: verification REJECTED', $ctx + ['mode' => $mode]);

                    return response('Forbidden', 403);
                }

                Log::info('WA webhook: verification OK', $ctx);

                return response($challenge, 200); // Meta expects raw challenge
            }

            // === POST: Incoming notifications
            if ($request->isMethod('post')) {
                $payload = $request->json()->all() ?? [];

                // Optional: verify HMAC signature
                $sig256 = (string) $request->header('X-Hub-Signature-256', '');
                $sig = (string) $request->header('X-Hub-Signature', ''); // legacy

                if ($appSecret !== '' && ($sig256 !== '' || $sig !== '')) {
                    $raw = $request->getContent();

                    $expected256 = 'sha256='.hash_hmac('sha256', $raw, $appSecret);
                    $expected = 'sha1='.hash_hmac('sha1', $raw, $appSecret);

                    $ok = false;
                    if ($sig256 !== '') {
                        $ok = hash_equals($expected256, $sig256);
                    } elseif ($sig !== '') {
                        $ok = hash_equals($expected, $sig);
                    }

                    if (! $ok) {
                        Log::warning('WA webhook: signature verification FAILED', $ctx + [
                            'sig256' => $this->maskHash($sig256),
                            'sig' => $this->maskHash($sig),
                        ]);

                        return response('Invalid signature', 401);
                    }
                }

                $meta = $this->extractMeta($payload);
                Log::info('WA webhook: POST received', $ctx + $meta);

                // --- BOT PNID GUARD ------------------------------------------
                // Meta delivers events for EVERY WABA subscribed to this app —
                // including the sister "pharmacy" install. Only act on events
                // addressed to THIS install's own phone number; acknowledge
                // anything else with 200 (so Meta stops retrying) but never
                // reply to it or run the booking flow for it.
                $botPnid = (string) config('services.whatsapp.phone_number_id');
                $incomingPnid = (string) ($meta['phone_number_id'] ?? '');
                if ($botPnid !== '' && $incomingPnid !== '' && $incomingPnid !== $botPnid) {
                    Log::info('WA webhook: skipping non-bot phone_number_id', $ctx + [
                        'incoming' => $incomingPnid,
                        'bot' => $botPnid,
                    ]);

                    return response('ok', 200);
                }

                // Idempotency on first message id.
                $wamid = data_get($payload, 'entry.0.changes.0.value.messages.0.id');
                $from = (string) (data_get($payload, 'entry.0.changes.0.value.messages.0.from') ?? '');

                if ($wamid) {
                    if (WAMessageLog::where('wa_message_id', $wamid)->exists()) {
                        Log::info('WA webhook: duplicate wamid, skipping', $ctx + ['wamid' => $wamid]);

                        return response()->json(['ok' => true]);
                    }

                    WAMessageLog::create([
                        'wa_message_id' => $wamid,
                        'phone' => $from,
                        'payload' => $payload,
                        'status' => 'processed',
                    ]);
                }

                $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
                if (! empty($value['statuses'])) {
                    $statuses = (array) ($value['statuses'] ?? []);

                    // Log count first
                    Log::info('WA status', $ctx + ['n' => count($statuses)]);

                    // Persist each status event (usually 1, but can be more)
                    foreach ($statuses as $s) {
                        $wamid = (string) ($s['id'] ?? '');
                        $recipient = (string) ($s['recipient_id'] ?? '');
                        $status = (string) ($s['status'] ?? 'status');

                        // Keep your detailed log (what you already added)
                        Log::info('WA status detail', $ctx + [
                            'id' => $wamid ?: null,
                            'status' => $status ?: null,
                            'timestamp' => $s['timestamp'] ?? null,
                            'recipient_id' => $recipient ?: null,
                            'conversation' => $s['conversation'] ?? null,
                            'pricing' => $s['pricing'] ?? null,
                            'errors' => $s['errors'] ?? null,
                        ]);

                        if ($wamid === '') {
                            continue;
                        }

                        // Upsert by wa_message_id (unique index exists)
                        try {
                            \App\Models\WAMessageLog::updateOrCreate(
                                ['wa_message_id' => $wamid],
                                [
                                    'phone' => $recipient !== '' ? $recipient : 'unknown',
                                    'payload' => $payload, // store last status payload (helps debugging)
                                    'status' => $status !== '' ? $status : 'status',
                                ]
                            );
                        } catch (\Throwable $e) {
                            Log::warning('WA status: failed to persist', $ctx + [
                                'id' => $wamid,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // Sync the bulk-campaign recipient so delivery analytics + failure
                        // reasons reflect what Meta actually reported (not just the 200 send).
                        try {
                            $rec = \App\Wa\Hub\Models\PromotionalCampaignRecipient::where('wa_message_id', $wamid)->first();
                            if ($rec) {
                                $at = ! empty($s['timestamp']) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $s['timestamp']) : now();
                                $rank = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

                                if ($status === 'failed') {
                                    $err = $s['errors'][0] ?? [];
                                    $code = (int) ($err['code'] ?? 0);
                                    // 131049 / 130472 = "healthy-ecosystem" engagement throttle → limited, not a hard fail.
                                    $rec->status = in_array($code, [131049, 130472], true) ? 'limited' : 'failed';
                                    $rec->wa_error_code = (string) $code;
                                    $rec->wa_error_title = $err['title'] ?? 'Failed';
                                    $rec->error_message = data_get($err, 'error_data.details') ?: ($err['message'] ?? $rec->error_message);
                                } elseif ($status === 'read') {
                                    $rec->read_at = $rec->read_at ?: $at;
                                    $rec->delivered_at = $rec->delivered_at ?: $at;
                                    $rec->status = 'read';
                                } elseif ($status === 'delivered') {
                                    $rec->delivered_at = $rec->delivered_at ?: $at;
                                    if (($rank['delivered']) >= ($rank[$rec->status] ?? 0)) {
                                        $rec->status = 'delivered';
                                    }
                                } elseif ($status === 'sent') {
                                    $rec->sent_at = $rec->sent_at ?: $at;
                                    if (($rank['sent']) >= ($rank[$rec->status] ?? 0)) {
                                        $rec->status = 'sent';
                                    }
                                }
                                $rec->save();
                            }
                        } catch (\Throwable $e) {
                            Log::warning('WA status: recipient sync failed', $ctx + ['id' => $wamid, 'error' => $e->getMessage()]);
                        }
                    }

                    return response('ok', 200);
                }

                if (empty($value['messages'])) {
                    return response('ok', 200);
                }

                /* ---------------- Rate limit / cooldown (admin-configurable) ---------------- */
                $rlEnabled = (bool) (Sys::get('whatsapp.rate_limit.enabled') ?? true);
                $winSecs = (int) (Sys::get('whatsapp.rate_limit.window_seconds') ?? 20);
                $limit = (int) (Sys::get('whatsapp.rate_limit.limit') ?? 3);
                $coolSecs = (int) (Sys::get('whatsapp.rate_limit.cooldown_seconds') ?? 30);
                $msgEnTpl = (string) (Sys::get('whatsapp.rate_limit.message_en') ?? 'You’re sending messages too quickly. Please try again in {seconds}s.');
                $msgArTpl = (string) (Sys::get('whatsapp.rate_limit.message_ar') ?? 'تم تقييد الرسائل مؤقتًا بسبب كثرة الإرسال. الرجاء المحاولة بعد {seconds} ثانية.');

                if ($rlEnabled && $from !== '') {
                    $lockKey = "wa:lock:{$from}";
                    $countKey = "wa:rl:count:{$from}";
                    $coolKey = "wa:rl:cool:{$from}";
                    $untilKey = "wa:rl:until:{$from}";
                    $noticeKey = "wa:rl:notice:{$from}";

                    // Get an atomic lock for this user. Wait 10s max.
                    $lock = Cache::lock($lockKey, 10);

                    if ($lock->get()) { // Successfully acquired the lock
                        try {
                            // If on cooldown, notify (once) and stop.
                            if (Cache::has($coolKey)) {
                                $untilTs = (int) (Cache::get($untilKey) ?? (now()->timestamp + $coolSecs));
                                $secsLeft = max(1, $untilTs - now()->timestamp);

                                if (Cache::add($noticeKey, 1, now()->addSeconds($secsLeft))) {
                                    $incomingText = (string) data_get($payload, 'entry.0.changes.0.value.messages.0.text.body', '');
                                    $isAr = $this->looksArabic($incomingText);

                                    $tmpl = $isAr ? $msgArTpl : $msgEnTpl;
                                    $msg = str_replace('{seconds}', (string) $secsLeft, $tmpl);

                                    $api = app(WhatsAppApiServiceFactory::class)->make();
                                    (new WhatsAppSender($api))->sendTextMessage($from, $msg);
                                    Log::info('WA rate-limit: cooling down user', $ctx + ['from' => $from, 'secs_left' => $secsLeft]);
                                }

                                return response()->json(['ok' => true, 'cooldown' => $secsLeft]);
                            }

                            // --- This is the new atomic logic ---
                            // Get the current count from the cache.
                            $count = (int) Cache::get($countKey, 0);
                            $count++; // Increment our count

                            // Check if we are over the limit
                            if ($count > $limit) {
                                $until = now()->addSeconds($coolSecs);
                                Cache::put($coolKey, 1, $until); // Start cooldown
                                Cache::put($untilKey, $until->timestamp, $until);
                                Cache::forget($countKey); // Reset the counter

                                $incomingText = (string) data_get($payload, 'entry.0.changes.0.value.messages.0.text.body', '');
                                $isAr = $this->looksArabic($incomingText);

                                $tmpl = $isAr ? $msgArTpl : $msgEnTpl;
                                $msg = str_replace('{seconds}', (string) $coolSecs, $tmpl);

                                $api = app(WhatsAppApiServiceFactory::class)->make();
                                (new WhatsAppSender($api))->sendTextMessage($from, $msg);
                                Log::info('WA rate-limit: user entered cooldown', $ctx + ['from' => $from, 'cooldown' => $coolSecs]);

                                return response()->json(['ok' => true, 'cooldown' => $coolSecs]);
                            }

                            // Not over limit, so save the new count with the window expiry
                            Cache::put($countKey, $count, now()->addSeconds($winSecs));
                            // --- End of new logic ---

                        } finally {
                            $lock->release(); // Always release the lock
                        }
                    } else {
                        // Could not get the lock.
                        // This means another request is *already* being processed for this user.
                        // This is fine, we can just log it and drop this (duplicate) request.
                        Log::info('WA rate-limit: lock busy, dropping request', $ctx + ['from' => $from]);

                        return response()->json(['ok' => true, 'status' => 'busy']);
                    }
                }
                /* --------------------------------------------------------------------------- */

                // Forward whole payload to the flow handler.
                $bookingFlow->handle($payload);

                Log::info('WA webhook: processed OK', $ctx);

                return response()->noContent(); // 204
            }
        } catch (\Throwable $e) {
            Log::error('WA webhook: handler error', $ctx + [
                'exception' => $e->getMessage(),
                'trace_top' => collect(explode("\n", $e->getTraceAsString()))->take(5)->implode("\n"),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }

        Log::warning('WA webhook: unsupported method', $ctx);

        return response('Unsupported method', 405);
    }

    /** Quick Arabic detector for message language pivot. */
    private function looksArabic(?string $text): bool
    {
        if (! $text) {
            return false;
        }

        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /**
     * Mask long HMAC signatures like "sha256=abcd...".
     */
    private function maskHash(?string $sig, int $keep = 8): ?string
    {
        if ($sig === null || $sig === '') {
            return $sig;
        }

        if (Str::startsWith($sig, 'sha256=')) {
            $hex = substr($sig, 7);
            if (strlen($hex) <= $keep * 2) {
                return 'sha256='.$hex;
            }

            return 'sha256='.substr($hex, 0, $keep).'…'.substr($hex, -$keep);
        }

        if (Str::startsWith($sig, 'sha1=')) {
            $hex = substr($sig, 5);
            if (strlen($hex) <= $keep * 2) {
                return 'sha1='.$hex;
            }

            return 'sha1='.substr($hex, 0, $keep).'…'.substr($hex, -$keep);
        }

        return $this->mask($sig, 6, 6);
    }

    /**
     * Mask a string, keeping head/tail visible.
     */
    private function mask(?string $value, int $head = 3, int $tail = 3): ?string
    {
        if ($value === null) {
            return null;
        }

        $len = strlen($value);
        if ($len <= $head + $tail) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, $head)
            .str_repeat('*', $len - $head - $tail)
            .substr($value, -$tail);
    }

    /**
     * Pull useful identifiers from the WA payload for logging.
     */
    private function extractMeta(array $p): array
    {
        $entry = $p['entry'][0] ?? [];
        $change = $entry['changes'][0] ?? [];
        $value = $change['value'] ?? [];
        $msgs = $value['messages'] ?? null;
        $stats = $value['statuses'] ?? null;
        $meta = $value['metadata'] ?? [];

        return [
            'waba_id' => $entry['id'] ?? null,
            'phone_number_id' => $meta['phone_number_id'] ?? null,
            'display_phone' => $meta['display_phone_number'] ?? null,
            'has_messages' => is_array($msgs),
            'has_statuses' => is_array($stats),
            'message_types' => $msgs ? collect($msgs)->pluck('type')->unique()->values()->all() : null,
            'status_samples' => $stats ? collect($stats)->pluck('status')->unique()->values()->all() : null,
            'conversation_origin' => data_get($stats, '0.conversation.origin.type'),
            'message_id_sample' => data_get($msgs, '0.id') ?? data_get($stats, '0.id'),
        ];
    }
}
