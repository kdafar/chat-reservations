<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generic OTP request + verify service.
 *
 * Channels are pluggable (whatsapp today; sms/email later). Codes are
 * hashed at rest. Each (purpose, recipient) keeps one live code at a time
 * — requesting a new code soft-invalidates older unverified ones by
 * marking them expired.
 *
 * Errors during send are logged and the code is still persisted, so
 * /verify can succeed even if the carrier dropped the message —
 * mirrors the "send is best-effort, code is the source of truth" approach
 * used elsewhere in this codebase. In dev with no template approved,
 * set CLINIC_OTP_DEV_LOG=true to dump codes to laravel.log for manual e2e.
 */
class OtpService
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    public const TTL_SECONDS = 600; // 10 minutes

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(protected WhatsAppSender $wa) {}

    /**
     * Generate, persist, and send a fresh OTP. Returns the code's expires_at.
     *
     * @throws RuntimeException When the resend cooldown is still active.
     */
    public function request(
        string $channel,
        string $purpose,
        string $recipient,
        ?string $ip = null,
        array $meta = [],
    ): Carbon {
        $recipient = $this->normalizeRecipient($channel, $recipient);

        $recent = OtpCode::query()
            ->where('purpose', $purpose)
            ->where('recipient', $recipient)
            ->whereNull('verified_at')
            ->where('created_at', '>=', now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
            ->latest('id')
            ->first();

        if ($recent) {
            $elapsed = now()->getTimestamp() - $recent->created_at->getTimestamp();
            $waitFor = max(1, self::RESEND_COOLDOWN_SECONDS - $elapsed);
            throw new RuntimeException("Please wait {$waitFor}s before requesting another code.");
        }

        // Soft-invalidate any older unverified codes for this purpose+recipient.
        OtpCode::query()
            ->where('purpose', $purpose)
            ->where('recipient', $recipient)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $code = $this->generateCode();
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        OtpCode::create([
            'channel' => $channel,
            'purpose' => $purpose,
            'recipient' => $recipient,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'verified_at' => null,
            'ip' => $ip,
            'meta' => $meta ?: null,
        ]);

        $this->dispatch($channel, $purpose, $recipient, $code);

        return $expiresAt;
    }

    /**
     * Verify a submitted code. Increments attempts on miss; marks verified_at
     * on hit. Returns true on success, false on miss/expired/exhausted.
     */
    public function verify(string $purpose, string $recipient, string $code): bool
    {
        $recipient = $this->normalizeRecipient(null, $recipient);
        $code = trim($code);

        $row = OtpCode::query()
            ->where('purpose', $purpose)
            ->where('recipient', $recipient)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $row) {
            return false;
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            // Burn it so subsequent attempts can't keep guessing.
            $row->forceFill(['expires_at' => now()])->save();
            return false;
        }

        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');
            return false;
        }

        $row->forceFill(['verified_at' => now()])->save();

        return true;
    }

    /**
     * True when a non-expired verified code exists for this purpose+recipient.
     * The "consumed?" question is left to callers — they can mark the code
     * by their own flag, or simply expire it after use.
     */
    public function hasVerified(string $purpose, string $recipient, int $withinSeconds = 1800): bool
    {
        $recipient = $this->normalizeRecipient(null, $recipient);

        return OtpCode::query()
            ->where('purpose', $purpose)
            ->where('recipient', $recipient)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subSeconds($withinSeconds))
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Burn the latest verified code for this purpose+recipient so it can't
     * be reused. Call this after the OTP-gated action succeeds.
     */
    public function consume(string $purpose, string $recipient): void
    {
        $recipient = $this->normalizeRecipient(null, $recipient);

        OtpCode::query()
            ->where('purpose', $purpose)
            ->where('recipient', $recipient)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);
    }

    protected function normalizeRecipient(?string $channel, string $recipient): string
    {
        $recipient = trim($recipient);

        if ($channel === self::CHANNEL_EMAIL) {
            return strtolower($recipient);
        }

        // For phone-based channels (whatsapp/sms) strip everything except digits.
        $digits = preg_replace('/\D+/', '', $recipient);

        return $digits !== '' ? $digits : $recipient;
    }

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send the code via the chosen channel. WhatsApp uses the
     * `clinic_booking_otp_v1` template if available; on failure (template
     * not approved, network error, etc.) we fall back to a plain text
     * message. In dev (CLINIC_OTP_DEV_LOG=true) we also log the code so
     * the flow is e2e-testable without a real WA send.
     */
    protected function dispatch(string $channel, string $purpose, string $recipient, string $code): void
    {
        if ((bool) env('CLINIC_OTP_DEV_LOG', false)) {
            Log::info("[OtpService] DEV CODE for {$purpose}/{$recipient}: {$code}");
        }

        if ($channel !== self::CHANNEL_WHATSAPP) {
            Log::warning('[OtpService] non-whatsapp channel requested but only whatsapp is wired', [
                'channel' => $channel,
                'purpose' => $purpose,
            ]);
            return;
        }

        $templateName = (string) config('clinic.booking_otp_template', 'clinic_booking_otp_v1');
        $templateLang = (string) config('clinic.booking_otp_template_lang', 'en');
        $ttlMinutes = (int) ceil(self::TTL_SECONDS / 60);

        try {
            $sent = $this->wa->sendTemplate(
                $recipient,
                $templateName,
                $templateLang,
                [
                    ['type' => 'text', 'text' => $code],
                    ['type' => 'text', 'text' => (string) $ttlMinutes],
                ]
            );

            if ($sent) {
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('[OtpService] WA template send threw', [
                'template' => $templateName,
                'recipient' => $recipient,
                'err' => $e->getMessage(),
            ]);
        }

        // Fallback: plain text so dev / staging still gets the code.
        try {
            $this->wa->sendTextMessage(
                $recipient,
                "Your verification code is {$code}. It expires in {$ttlMinutes} minutes."
            );
        } catch (\Throwable $e) {
            Log::warning('[OtpService] WA plain-text fallback failed', [
                'recipient' => $recipient,
                'err' => $e->getMessage(),
            ]);
        }
    }
}
