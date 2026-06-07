<?php

namespace App\Wa\Helpers;

use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Throwable;

class WhatsAppCrypto
{
    /**
     * ──────────────────────────────────────────────────────────────────────────────
     * WhatsApp Flows Crypto: TROUBLESHOOTING GUIDE (DO NOT REMOVE)
     * ──────────────────────────────────────────────────────────────────────────────
     *
     * If you ever see:
     *   - "Failed to decrypt response"
     *   - "Tag mismatch"
     *   - Health-check not green
     *   - RSA/AES/IV related errors in logs
     *
     * 99% of the time, the issue is one of these:
     *
     * 1. PUBLIC/PRIVATE KEY MISMATCH
     *    - The private key file at config('services.whatsapp.private_key_path')
     *      MUST match the public key uploaded to WhatsApp Manager for this endpoint.
     *    - If in doubt, extract the public key:
     *         openssl rsa -in /path/to/private.pem -pubout
     *      and re-upload it in WhatsApp Manager > Flows > Edit Endpoint.
     *
     * 2. IV (initialization vector) HANDLING
     *    - Always use the *exact* Base64 string from the incoming request's 'initial_vector'.
     *    - Never truncate or mutate the IV. If WhatsApp sends a 12 or 16 byte IV, flip ALL bytes.
     *    - Store both the base64 and binary IV in decrypt(); always use base64 for encrypt().
     *
     * 3. ENCRYPTION/RESPONSE FORMAT
     *    - metaEncrypt/encrypt() MUST flip ALL bytes of the IV, not just 12.
     *    - Response must be: BASE64( cipher || tag ) (do NOT prepend the IV!).
     *    - Response body must be plain text, not JSON.
     *
     * 4. HEALTH CHECK PAYLOAD
     *    - The correct JSON for health check is: {"data":{"status":"active"}}
     *      (No extra keys or status fields.)
     *
     * 5. CACHING/DEPLOYMENT
     *    - After changing keys or code, clear opcache and reload PHP-FPM to avoid old code/keys.
     *
     * 6. DEBUGGING
     *    - Log IV lengths, key lengths, and ensure logs never truncate or alter IV/base64 strings.
     *    - Check your endpoint is running the correct code (no deployment lag).
     *
     * IF YOU CHANGE PRIVATE KEYS OR ENDPOINT CODE:
     *    - Always re-upload the public key to WhatsApp Manager for this Flow.
     *    - Wait 2–5 minutes after any key upload before re-testing.
     *
     * This block is your life-saver. Read it before spending hours debugging!
     * For reference/refreshers, see Meta’s official Flows API docs and the original chat notes.
     * ──────────────────────────────────────────────────────────────────────────────
     */

    /* ───────────────── DECRYPT ───────────────── */

    public static function decrypt(array $req): ?array
    {
        foreach (['encrypted_flow_data', 'encrypted_aes_key', 'initial_vector'] as $f) {
            if (empty($req[$f])) {
                return null;
            }
        }

        try {
            $aesKey = self::rsaDecrypt($req['encrypted_aes_key']);
            if (! $aesKey || strlen($aesKey) !== 16) {
                Log::error('AES key length not 16');

                return null;
            }
            $iv_bin = base64_decode($req['initial_vector']);
            $iv_b64 = trim($req['initial_vector']); // store exact original

            $cipherRaw = base64_decode($req['encrypted_flow_data']);
            $tag = substr($cipherRaw, -16);
            $cipher = substr($cipherRaw, 0, -16);

            $aes = new AES('gcm');
            $aes->setKey($aesKey);
            $aes->setNonce($iv_bin);
            $aes->setTag($tag);

            $json = $aes->decrypt($cipher);
            if ($json === false) {
                Log::error('AES decrypt failed');

                return null;
            }

            $data = json_decode($json, true);
            if (json_last_error()) {
                Log::error('JSON decode error', ['err' => json_last_error_msg()]);

                return null;
            }

            // Save BOTH!
            $data['_wa_aes_key'] = $aesKey;
            $data['_wa_iv_bin'] = $iv_bin;
            $data['_wa_iv_b64'] = $iv_b64; // exact string

            return $data;

        } catch (Throwable $e) {
            Log::error('Decrypt exception', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /* ──────────────── ENCRYPT (spec) ──────────────── */

    /**
     * Build the single Base-64 string Meta expects:
     *   flippedIV ∥ cipher ∥ tag
     */
    public static function encrypt(string $plainJson, string $aesKey, string $reqIvB64): string
    {
        $reqIv = base64_decode($reqIvB64);                  // any length
        $flippedIv = $reqIv ^ str_repeat("\xFF", strlen($reqIv)); // flip ALL bytes

        $aes = new AES('gcm');
        $aes->setKey($aesKey);
        $aes->setNonce($flippedIv);

        $cipher = $aes->encrypt($plainJson);
        $tag = $aes->getTag();

        return base64_encode($cipher.$tag);
    }

    /* ───────────── helper: RSA-OAEP-SHA-256 ───────────── */

    private static function rsaDecrypt(string $cipherB64): ?string
    {
        $pemPath = config('services.whatsapp.private_key_path')
            ?? base_path('storage/wa_keys/data_api_private.pem');
        $pass = config('services.whatsapp.private_key_passphrase');

        try {
            $priv = PublicKeyLoader::loadPrivateKey(file_get_contents($pemPath), $pass)
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            return $priv->decrypt(base64_decode(trim($cipherB64)));
        } catch (Throwable $e) {
            Log::error('RSA decrypt failed', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    public static function metaDecrypt(array $req): ?array
    {
        foreach (['encrypted_flow_data', 'encrypted_aes_key', 'initial_vector'] as $f) {
            if (empty($req[$f])) {
                return null;
            }
        }

        /* 1️⃣  RSA-OAEP-SHA-256 → AES-128 key */
        $aesKey = rsa_oaep_sha256_decrypt(base64_decode($req['encrypted_aes_key']));
        if (! $aesKey || strlen($aesKey) !== 16) {
            return null;
        }

        /* 2️⃣  AES-128-GCM decrypt */
        $iv = base64_decode($req['initial_vector']);
        $raw = base64_decode($req['encrypted_flow_data']);
        $tag = substr($raw, -16);
        $cipher = substr($raw, 0, -16);

        $aes = new AES('gcm');
        $aes->setKey($aesKey);
        $aes->setNonce($iv);
        $aes->setTag($tag);
        $json = $aes->decrypt($cipher);

        return $json === false ? null : [json_decode($json, true), $aesKey, $iv];
    }

    /** Encrypt response – returns ONE Base-64 string (cipher||tag) */
    public static function metaEncrypt(string $json, string $aesKey, string $reqIvB64): string
    {
        $reqIv = base64_decode($reqIvB64);
        $flip = $reqIv ^ str_repeat("\xFF", strlen($reqIv));   // Flip all bytes
        $aes = new AES('gcm');
        $aes->setKey($aesKey);
        $aes->setNonce($flip);
        $cipher = $aes->encrypt($json);
        $tag = $aes->getTag();

        return base64_encode($cipher.$tag); // Output: cipher||tag (NO IV prepended)
    }
}
