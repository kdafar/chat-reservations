<?php

// App/Services/WAFlow/FlowCrypto.php

namespace App\Services\WAFlow;

use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Exception\NoKeyLoadedException;

class FlowCrypto
{
    private string $keyPath;

    private ?string $pass;

    public function __construct()
    {
        $this->keyPath = $this->resolvePath(config('services.whatsapp.flows.private_key_path', 'whatsapp-flows/private.pem'));
        $this->pass = config('services.whatsapp.flows.private_key_passphrase');
    }

    public function decrypt(string $encDataB64, string $encKeyB64, string $ivB64): ?FlowRequest
    {
        $keyChecksum = 'unknown';
        try {
            if (is_readable($this->keyPath)) {
                $keyChecksum = md5((string) file_get_contents($this->keyPath));
            } else {
                Log::warning('[FlowCrypto] Private key not readable for checksum logging.', ['path' => $this->keyPath]);
            }

            $encKey = base64_decode($encKeyB64, true);
            $iv = base64_decode($ivB64, true);
            $blob = base64_decode($encDataB64, true);
            if ($encKey === false || $iv === false || $blob === false) {

                return null;
            }
            if (($l = strlen($iv)) < 12 || $l > 32) {

                return null;
            }
            if (strlen($blob) < 17) {
                return null;
            }

            $priv = $this->loadPrivateKey();
            $aesKey = $priv->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256')->decrypt($encKey);
            if ($aesKey === false || strlen($aesKey) !== 16) {

                return null;
            }

            $tag = substr($blob, -16);
            $ct = substr($blob, 0, -16);
            $json = openssl_decrypt($ct, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '');
            if ($json === false) {

                return null;
            }

            $arr = json_decode($json, true) ?: [];

            return FlowRequest::fromArray($arr, $aesKey, $iv);

        } catch (\Throwable $e) {
            Log::warning('[FlowCrypto] decrypt failed', [
                'err' => $e->getMessage(),
                'key_path' => $this->keyPath,
                'key_md5' => $keyChecksum,
                'encKey_b64_len' => strlen($encKeyB64),
                'iv_b64_len' => strlen($ivB64),
                'blob_b64_len' => strlen($encDataB64),
            ]);

            return null;
        }
    }

    public function encrypt(array $resp, string $aesKey, string $requestIv): ?string
    {
        // XOR IV for response (Meta spec)
        $ivResp = $requestIv ^ str_repeat("\xFF", strlen($requestIv));
        $plaintext = json_encode($resp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // ^ never JSON_FORCE_OBJECT

        // Optional: debug types once more (safe, small)
        $dbg = json_decode($plaintext, true);

        $tagOut = '';
        $cipher = openssl_encrypt($plaintext, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $ivResp, $tagOut, '');
        if ($cipher === false) {
            Log::error('Flows: GCM encrypt failed');

            return null;
        }

        return base64_encode($cipher.$tagOut);
    }

    private function loadPrivateKey(): \phpseclib3\Crypt\RSA\PrivateKey
    {
        if (! is_readable($this->keyPath)) {
            Log::error('Flows: private key file not readable', ['path' => $this->keyPath]);
            abort(500, 'Private key not found or unreadable');
        }

        $pem = file_get_contents($this->keyPath);

        try {
            return PublicKeyLoader::loadPrivateKey($pem, $this->pass ?: null);
        } catch (NoKeyLoadedException $e) {
            Log::error('Flows: cannot parse private key', ['path' => $this->keyPath, 'has_passphrase' => $this->pass !== null]);
            abort(500, 'Invalid private key or passphrase');
        }
    }

    private function resolvePath(string $p): string
    {
        $abs = str_starts_with($p, '/') || (bool) preg_match('/^[A-Za-z]:\\\\/', $p);

        return $abs ? $p : storage_path($p);
    }
}
