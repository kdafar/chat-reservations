<?php

namespace App\Wa\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MetaWhatsAppController extends Controller
{
    public function exchangeToken(Request $request)
    {
        $rid = (string) Str::uuid(); // request id for correlation

        Log::withContext([
            'rid' => $rid,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'user' => Auth::id(),
        ]);

        $appId = (string) config('services.meta.app_id');
        $appSecret = (string) config('services.meta.app_secret');
        $redirectUri = 'https://bannerkw.com/api/meta/callback'; // MUST match App Dashboard

        Log::info('[META:EAL] start exchange', [
            'has_code' => $request->has('code'),
            'app_id' => $this->mask($appId),
            'redirect' => $redirectUri,
        ]);

        if (! $request->has('code')) {
            Log::warning('[META:EAL] missing ?code param');

            return response("OK (expects ?code= from Business Login) RID={$rid}", 200);
        }

        $request->validate(['code' => 'required|string']);
        $code = $request->input('code');

        try {
            // 1) short-lived token
            $resp1 = Http::timeout(20)->retry(2, 500)->get(
                'https://graph.facebook.com/v24.0/oauth/access_token',
                [
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ]
            );

            Log::info('[META:EAL] step1 exchange', [
                'status' => $resp1->status(),
                'ok' => $resp1->ok(),
                'has_token' => isset($resp1->json()['access_token']),
                'expires_in' => $resp1->json()['expires_in'] ?? null,
                'error' => $resp1->json()['error']['message'] ?? null,
            ]);

            if ($resp1->failed() || ! isset($resp1->json()['access_token'])) {
                Log::error('[META:EAL] step1 failed', [
                    'status' => $resp1->status(),
                    'body' => $this->truncate(json_encode($resp1->json())),
                ]);

                return response()->json([
                    'status' => 'error',
                    'rid' => $rid,
                    'message' => 'Failed to get access token (step1).',
                ], 400);
            }

            $short = $resp1->json()['access_token'];

            // 2) long-lived token
            $resp2 = Http::timeout(20)->retry(2, 500)->get(
                'https://graph.facebook.com/oauth/access_token',
                [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $appId,
                    'client_secret' => $appSecret,
                    'fb_exchange_token' => $short,
                ]
            );

            Log::info('[META:EAL] step2 long-lived', [
                'status' => $resp2->status(),
                'ok' => $resp2->ok(),
                'has_token' => isset($resp2->json()['access_token']),
                'expires_in' => $resp2->json()['expires_in'] ?? null,
                'error' => $resp2->json()['error']['message'] ?? null,
            ]);

            if ($resp2->failed() || ! isset($resp2->json()['access_token'])) {
                Log::error('[META:EAL] step2 failed', [
                    'status' => $resp2->status(),
                    'body' => $this->truncate(json_encode($resp2->json())),
                ]);

                return response()->json([
                    'status' => 'error',
                    'rid' => $rid,
                    'message' => 'Failed to get long-lived token (step2).',
                ], 400);
            }

            $long = $resp2->json()['access_token'];
            $expires = $resp2->json()['expires_in'] ?? null;
            $user = Auth::user();

            // Save token (and expiry if column exists)
            $update = ['whatsapp_access_token' => $long];
            if ($expires && Schema::hasColumn($user->getTable(), 'whatsapp_access_token_expires_at')) {
                $update['whatsapp_access_token_expires_at'] = now()->addSeconds((int) $expires);
            }
            $user->update($update);

            Log::info('[META:EAL] token saved', [
                'user_id' => $user->id,
                'token_len' => strlen($long),
                'token_peek' => $this->mask($long),
                'expires_s' => $expires,
            ]);

            return response()->json([
                'status' => 'success',
                'rid' => $rid,
            ]);

        } catch (\Throwable $e) {
            Log::error('[META:EAL] exception', [
                'ex' => $e->getMessage(),
                'trace' => $this->truncate($e->getTraceAsString(), 2000),
            ]);

            return response()->json([
                'status' => 'error',
                'rid' => $rid,
                'message' => 'Unexpected error during exchange.',
            ], 500);
        }
    }

    private function mask(?string $s, int $start = 6, int $end = 4): ?string
    {
        if (! $s) {
            return $s;
        }
        $len = strlen($s);
        if ($len <= $start + $end) {
            return str_repeat('*', $len);
        }

        return substr($s, 0, $start).str_repeat('*', max(0, $len - $start - $end)).substr($s, -$end);
    }

    private function truncate(?string $s, int $limit = 800): ?string
    {
        if (! $s) {
            return $s;
        }

        return strlen($s) > $limit ? substr($s, 0, $limit).'…' : $s;
    }

    public function sessionInfo(Request $request)
    {
        // The page posts the raw WA_EMBEDDED_SIGNUP payload as { session: {...} }
        $payload = $request->input('session', []);

        Log::info('[ESI] session info received', [
            'user_id' => Auth::id(),
            'type' => data_get($payload, 'type'),
            'event' => data_get($payload, 'event'),
            'waba_id' => data_get($payload, 'data.waba_id'),
            'phone_id' => data_get($payload, 'data.phone_number_id'),
            'step' => data_get($payload, 'data.current_step'),
            // don’t log entire payload in prod if sensitive; this is fine for review
        ]);

        // Optional: store IDs on the current user if columns exist
        $user = Auth::user();
        if ($user) {
            $updates = [];
            $wabaId = (string) data_get($payload, 'data.waba_id');
            $phoneId = (string) data_get($payload, 'data.phone_number_id');

            if ($wabaId && Schema::hasColumn($user->getTable(), 'whatsapp_waba_id')) {
                $updates['whatsapp_waba_id'] = $wabaId;
            }
            if ($phoneId && Schema::hasColumn($user->getTable(), 'whatsapp_phone_number_id')) {
                $updates['whatsapp_phone_number_id'] = $phoneId;
            }

            if (! empty($updates)) {
                try {
                    $user->forceFill($updates)->save();
                    Log::info('[ESI] session info saved on user', array_keys($updates));
                } catch (\Throwable $e) {
                    Log::warning('[ESI] failed saving session info on user', ['err' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
