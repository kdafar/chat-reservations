<?php

namespace App\Services;

use App\Models\BulkInviteCampaign;
use App\Models\BulkInviteCampaignRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Netflie\WhatsAppCloudApi\Response\ResponseException;

class WhatsAppInviteSender
{
    public function __construct(protected WhatsAppApiService $wa) {}

    /**
     * Sends one template message and returns WhatsApp message ID (WAMID).
     *
     * @throws \RuntimeException on delivery failures with a human-readable reason.
     */
    public function send(BulkInviteCampaign $campaign, BulkInviteCampaignRecipient $recipient): string
    {
        $to = $this->normalizeMsisdn((string) $recipient->msisdn);

        // --- LANGUAGE (force code string) ---
        $language = $this->normalizeLanguage(
            $recipient->locale ?: ($campaign->default_locale ?: 'en_US')
        );

        // --- TEMPLATE NAME ---
        $template = (string) $campaign->template_name;

        // --- BODY VARS (only if BODY has placeholders) ---
        [$hasBodySlots, $bodyText] = $this->extractBodyInfo($campaign);
        $vars = $campaign->template_variables;

        if (is_string($vars)) {
            $decoded = json_decode($vars, true);
            $vars = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($vars)) {
            $vars = [];
        }

        $bodyParams = [];
        if ($hasBodySlots && ! empty($vars)) {
            if ($this->isAssoc($vars)) {
                ksort($vars, SORT_NATURAL);
                $vars = array_values($vars);
            }
            foreach ($vars as $v) {
                $text = $this->toScalarText($v);
                $bodyParams[] = ['type' => 'text', 'text' => $text];
            }
        }

        // --- HEADER (image link if available) ---
        $headerParams = [];
        if (! empty($campaign->header_image_path)) {
            $link = (string) Storage::disk('public')->url($campaign->header_image_path);
            $headerParams[] = ['type' => 'image', 'image' => ['link' => $link]];
        }

        // --- BUTTONS (none for now) ---
        $buttonParams = [];

        // --- PREVIEW payload (for logs) ---
        $components = [];
        if (! empty($headerParams)) {
            $components[] = ['type' => 'HEADER', 'parameters' => $headerParams];
        }
        if (! empty($bodyParams)) {
            $components[] = ['type' => 'BODY',   'parameters' => $bodyParams];
        }
        // Buttons would go here if any.

        $preview = [
            'to' => $to,
            'template' => $template,
            'language' => $language,
            'components' => $components,
            'body_has_slots' => $hasBodySlots,
            'body_text_excerpt' => mb_substr($bodyText, 0, 120),
        ];

        Log::debug('[WA Invite] preflight', [
            'preview' => $preview,
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
        ]);

        // --- SEND via raw sender ---
        try {
            $resp = $this->wa->sendTemplateRaw(
                $to,
                $template,
                $language,     // string like "ar" or "en_US" (service should wrap as ["code"=>...])
                $headerParams, // array-of-parameters for HEADER
                $bodyParams,   // array-of-parameters for BODY
                $buttonParams  // ...
            );
        } catch (ResponseException $e) {
            $this->throwReadableMetaError($e);
        } catch (\Throwable $e) {
            Log::error('[WA Invite] sendTemplateRaw threw', [
                'ex' => get_class($e),
                'msg' => $this->safeStr($e->getMessage()),
                'code' => method_exists($e, 'getCode') ? $e->getCode() : null,
                'preview' => $preview,
            ]);
            throw new \RuntimeException('WhatsApp send failed: '.$this->safeStr($e->getMessage()));
        }

        Log::debug('[WA Invite] sendTemplateRaw response', ['resp' => $resp]);

        // success shape: messages[0].id
        $waId = data_get($resp, 'messages.0.id');
        if ($waId) {
            return (string) $waId;
        }

        // meta error
        if ($error = data_get($resp, 'error')) {
            $code = data_get($error, 'code');
            $details = data_get($error, 'error_data.details') ?: data_get($error, 'message');
            Log::error('[WA Invite] graph error', ['error' => $error, 'preview' => $preview]);
            throw new \RuntimeException('WhatsApp send failed: '.$this->safeStr($details ?: 'Unknown error').($code ? " (code {$code})" : ''));
        }

        // recipient not a WA user?
        $contact = data_get($resp, 'contacts.0');
        if ($contact && empty($contact['wa_id'])) {
            $status = $contact['status'] ?? 'invalid';
            throw new \RuntimeException("WhatsApp: recipient looks invalid ({$status}). Use E.164, e.g., +9655xxxxxxx.");
        }

        // dev-mode allowlist hint
        $rawJson = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($rawJson) && str_contains(strtolower($rawJson), 'allowed list')) {
            throw new \RuntimeException(
                'WhatsApp: recipient not in dev-mode allowed list. Add it under API Setup or switch the app to Live.'
            );
        }

        throw new \RuntimeException('WhatsApp send failed: missing message id. Raw: '.$this->safeStr($rawJson));
    }

    /* ---------------- helpers ---------------- */

    private function isAssoc(array $a): bool
    {
        return array_keys($a) !== range(0, count($a) - 1);
    }

    private function normalizeLanguage(string|array $raw): string
    {
        if (is_array($raw)) {
            $raw = (string) ($raw['code'] ?? $raw['value'] ?? 'en_US');
        } else {
            $raw = (string) $raw;
        }
        $raw = trim($raw);

        return $raw !== '' ? $raw : 'en_US';
    }

    /**
     * Returns [hasBodySlots(bool), bodyText(string)]
     */
    private function extractBodyInfo(BulkInviteCampaign $campaign): array
    {
        $td = $campaign->template_details;
        if (is_string($td)) {
            $td = json_decode($td, true);
        }
        if (! is_array($td)) {
            return [true, '']; // permissive
        }

        foreach ((array) data_get($td, 'components', []) as $c) {
            if (strtoupper((string) data_get($c, 'type')) === 'BODY') {
                $text = (string) data_get($c, 'text', '');

                return [str_contains($text, '{{'), $text];
            }
        }

        return [false, ''];
    }

    private function toScalarText(mixed $v): string
    {
        if (is_scalar($v) || $v === null) {
            return (string) $v;
        }

        // for arrays/objects, encode compactly
        return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function safeStr(mixed $v): string
    {
        return is_string($v) ? $v : $this->toScalarText($v);
    }

    protected function throwReadableMetaError(ResponseException $e): void
    {
        $msg = $e->getMessage();
        $code = null;
        $details = null;

        if (preg_match('/\{".*}\}$/s', (string) $msg, $m)) {
            $json = json_decode($m[0], true);
            $code = data_get($json, 'error.code');
            $details = data_get($json, 'error.error_data.details') ?: data_get($json, 'error.message');
        }

        if ((int) $code === 131030) {
            throw new \RuntimeException(
                'WhatsApp: Recipient phone number is not in the dev-mode allowed list. Add it under API Setup or switch to Live.'
            );
        }
        if ((int) $code === 100) {
            throw new \RuntimeException(
                'WhatsApp: Invalid template parameters. Check template name/language and header/body variables.'.($details ? " Details: {$details}" : '')
            );
        }
        if ((int) $code === 190) {
            throw new \RuntimeException('WhatsApp: Access token invalid/expired. Refresh your token.');
        }

        throw new \RuntimeException('WhatsApp send failed: '.$this->safeStr($details ?: $msg));
    }

    protected function normalizeMsisdn(string $raw): string
    {
        $n = preg_replace('/[\s\-\(\)]/', '', trim($raw));
        if (str_starts_with($n, '00')) {
            $n = '+'.substr($n, 2);
        }
        if (! str_starts_with($n, '+')) {
            $n = '+'.$n;
        }

        return $n;
    }
}
