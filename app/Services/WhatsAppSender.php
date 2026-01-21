<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Netflie\WhatsAppCloudApi\Message\Template\Component;

class WhatsAppSender
{
    public function __construct(protected WhatsAppApiService $api) {}

    /* ---------------------------------------------------------------
     | Text
     |---------------------------------------------------------------*/
    public function sendTextMessage(string $to, string $text): bool
    {
        // Let the API helper normalize newlines & preview_url
        return $this->api->sendTextMessage($to, $text);
    }

    /* ---------------------------------------------------------------
     | Read receipt
     |---------------------------------------------------------------*/
    public function markAsRead(string $messageId): bool
    {
        return $this->api->markAsRead($messageId);
    }

    /* ---------------------------------------------------------------
     | Buttons (<=3) with auto-fallback to List (>3)
     |---------------------------------------------------------------*/
    public function sendButtons(string $to, string $text, array $buttons, ?string $headerText = null): void
    {
        // Delegate all logic to the ApiService, which already handles the
        // button-to-list fallback and payload creation.
        // (Note: Your ApiService has no footerText, so we pass headerText)
        $this->api->sendButtonMessage(
            $to,
            $text,
            $buttons,
            $headerText,
            null // No footer text in the ApiService::sendButtonMessage signature
        );
    }

    /* ---------------------------------------------------------------
     | List (explicit)
     |---------------------------------------------------------------*/
    public function sendListMessage(
        string $to,
        string $headerText,
        string $bodyText,
        string $buttonLabel,
        array $rows,
        string $sectionTitle = 'Options',
        string $footerText = 'Select one'
    ): void {
        $mappedRows = array_map(function ($r) {
            return [
                'id' => (string) ($r['id'] ?? Str::uuid()),
                'title' => (string) ($r['title'] ?? $r['label'] ?? 'Item'),
                'description' => (string) ($r['desc'] ?? $r['description'] ?? ''),
            ];
        }, $rows);

        if (mb_strlen($buttonLabel) > 20) {
            $buttonLabel = mb_substr($buttonLabel, 0, 20);
        }

        $this->api->sendListRaw(
            $to,
            $headerText,
            $bodyText,
            $buttonLabel,
            [['title' => $sectionTitle, 'rows' => array_values($mappedRows)]],
            $footerText
        );
    }

    /* ---------------------------------------------------------------
     | Image
     |---------------------------------------------------------------*/
    public function sendImage(string $to, string $link, string $caption): bool
    {
        try {
            $res = $this->api->sendImageRaw($to, $link, $this->normalizeNewlines($caption));

            return empty($res['error']);
        } catch (\Throwable $e) {
            Log::warning('WA: sendImage exception', ['e' => $e->getMessage()]);

            return false;
        }
    }

    /* ---------------------------------------------------------------
     | Templates
     |---------------------------------------------------------------*/
    public function sendTemplate(string $to, string $name, string $langCode, array $bodyParams = []): bool
    {
        try {
            // new 6-arg signature: header, body, buttons
            $res = $this->api->sendTemplateRaw(
                $to,
                $name,
                $langCode,
                /* header */ [],
                /* body */ $bodyParams,
                /* buttons */ []
            );

            return empty($res['error']);
        } catch (\Throwable $e) {
            \Log::warning('WA: sendTemplate exception', ['e' => $e->getMessage()]);

            return false;
        }
    }

    public function sendTemplateAdvanced(
        string $to,
        string $name,
        string $langCode,
        array $headerParams = [],   // e.g. [['type'=>'image','image'=>['link'=>$url]]]
        array $bodyParams = [],
        array $buttonParams = []
    ): bool {
        try {
            $res = $this->api->sendTemplateRaw(
                $to,
                $name,
                $langCode,
                $headerParams,
                $bodyParams,
                $buttonParams
            );

            return empty($res['error']);
        } catch (\Throwable $e) {
            \Log::warning('WA: sendTemplateAdvanced exception', ['e' => $e->getMessage()]);

            return false;
        }
    }

    public function trySendTemplate(string $to, string $templateName, string $language, Component $components): bool
    {
        try {
            $this->api->sendTemplate($to, $templateName, $language, $components);

            return true;
        } catch (\Throwable $e) {
            Log::warning('WA template failed', [
                'template' => $templateName,
                'lang' => $language,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function makeTemplateBodyPositional(array $values): Component
    {
        $bodyParams = [];
        foreach (array_values($values) as $v) {
            $bodyParams[] = ['type' => 'text', 'text' => (string) $v];
        }

        return new Component([], $bodyParams, []);
    }

    public function makeTemplateVars(array $vars): Component
    {
        $bodyParams = array_map(
            fn ($value) => ['type' => 'text', 'text' => (string) $value],
            $vars
        );

        return new Component([], $bodyParams, []);
    }

    public function makeTemplateBodyComponent(array $textVariables): Component
    {
        return $this->makeTemplateVars($textVariables);
    }

    /* ---------------------------------------------------------------
     | Flows (delegates to API; returns bool)
     |---------------------------------------------------------------*/
    public function sendFlow(
        string $to,
        string $flowId,
        string $screen,
        array $data,
        string $cta,
        string $flowToken,
        ?string $locale = null
    ): bool {
        try {
            $json = $this->api->sendFlow(
                $to,
                $flowId,
                $cta,
                $flowToken,
                $screen,
                $data,
                config('services.whatsapp.flows.mode', 'published')
            );

            return $this->ok($json);
        } catch (\Throwable $e) {
            Log::warning('WA: sendFlow exception', ['e' => $e->getMessage()]);

            return false;
        }
    }

    /* ---------------------------------------------------------------
     | Helpers
     |---------------------------------------------------------------*/
    private function normalizeNewlines(string $t): string
    {
        $t = preg_replace('/\\\\n/', "\n", $t);   // turn "\n" into real newlines

        return preg_replace("/\n{3,}/", "\n\n", trim($t)); // collapse 3+ to 2
    }

    private function ok(array $json): bool
    {
        return ! isset($json['error']);
    }
}
