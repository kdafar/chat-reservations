<?php

namespace App\Services;

use App\Models\WhatsappSession;
use App\Models\WhatsappTrigger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles sending automated responses based on keywords and events
 * (Welcome, Finale, Fallback, Keywords).
 */
class WhatsappTriggerService
{
    /** @var \Illuminate\Database\Eloquent\Collection */
    protected $triggers;

    public function __construct(
        protected WhatsAppApiServiceFactory $waFactory,
        protected BookingFlowUiService $ui,
    ) {
        // Cache triggers for 5 minutes for performance
        $this->triggers = Cache::remember('whatsapp_triggers_active', 300, function () {
            return WhatsappTrigger::where('is_active', true)->get();
        });
    }

    /**
     * Attempts to find and send a response for a keyword trigger.
     *
     * @return bool True if a trigger was found and handled, false otherwise.
     */
    public function handleKeywordTrigger(WhatsappSession $session, string $text): bool
    {
        $locale = $this->lang($session);
        $cleanText = $this->normalizeKeyword($text);

        // Find the first matching keyword trigger
        $trigger = $this->triggers
            ->where('type', 'keyword')
            ->first(function (WhatsappTrigger $trigger) use ($cleanText) {
                // Check against a comma-separated list of keywords
                $keywords = array_map('trim', explode(',', strtolower($trigger->keyword ?? '')));

                return in_array($cleanText, $keywords);
            });

        if ($trigger) {
            Log::info('WA chat: Found keyword trigger.', ['keyword' => $cleanText, 'trigger_id' => $trigger->id]);
            $this->sendResponse($session, $trigger, $locale);

            return true; // A trigger was handled
        }

        return false; // No trigger was found
    }

    /**
     * Handles sending the 'welcome' message for new conversations.
     */
    public function handleWelcome(WhatsappSession $session): void
    {
        $locale = $this->lang($session);
        $trigger = $this->triggers->where('type', 'welcome')->first();

        if ($trigger) {
            Log::info('WA chat: Sending welcome message.', ['session_id' => $session->id]);
            $this->sendResponse($session, $trigger, $locale);
        } else {
            Log::warning('WA chat: No active "welcome" trigger found in database.');
        }
    }

    /**
     * Handles sending the 'finale' message after a successful booking.
     * You will need to call this manually from your Booking code (e.g., in WhatsAppFlowEndpointController
     * right after the booking is confirmed, before sending the CONFIRMATION screen).
     */
    public function handleFinale(WhatsappSession $session): void
    {
        $locale = $this->lang($session);
        $trigger = $this->triggers->where('type', 'finale')->first();

        if ($trigger) {
            Log::info('WA chat: Sending finale message.', ['session_id' => $session->id]);
            $this->sendResponse($session, $trigger, $locale);
        } else {
            Log::warning('WA chat: No active "finale" trigger found in database.');
        }
    }

    /**
     * Handles sending the 'fallback' message when no other logic matched.
     */
    public function handleFallback(WhatsappSession $session): void
    {
        $locale = $this->lang($session);
        $trigger = $this->triggers->where('type', 'fallback')->first();

        if ($trigger) {
            Log::info('WA chat: Sending fallback message.', ['session_id' => $session->id]);
            $this->sendResponse($session, $trigger, $locale);
        } else {
            Log::warning('WA chat: No active "fallback" trigger found in database.');
        }
    }

    /**
     * Sends the trigger's response to the user.
     */
    private function sendResponse(WhatsappSession $session, WhatsappTrigger $t, string $locale): void
    {
        $api = $this->waFactory->make();
        $sender = new \App\Services\WhatsAppSender($api);

        $to = $session->phone;
        $type = $t->response_type ?: 'text';
        $meta = (array) ($t->response_meta ?? []);

        $intro = $locale === 'ar'
            ? (string) ($t->response_message_ar ?? '')
            : (string) ($t->response_message_en ?? '');

        switch ($type) {
            case 'link':
                $url = (string) ($meta['link_url'] ?? '');
                $text = trim($intro.($intro && $url ? "\n" : '').$url);
                $sender->sendTextMessage($to, $text); // preview enabled in sender
                break;

            case 'image_upload':
                $disk = (string) ($meta['image_disk'] ?? 'public');
                $path = (string) ($meta['image_upload_path'] ?? $meta['image_path'] ?? '');
                if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                    $sender->sendTextMessage($to, $intro ?: '');
                    break;
                }
                $caption = $locale === 'ar' ? ($meta['caption_ar'] ?? '') : ($meta['caption_en'] ?? '');

                $mediaId = $meta['wa_media_id'] ?? null;
                if (! $mediaId) {
                    $abs = Storage::disk($disk)->path($path);
                    $mime = @mime_content_type($abs) ?: null;
                    $mediaId = $api->uploadMedia($abs, $mime);
                    $t->update(['response_meta' => array_merge($meta, ['wa_media_id' => $mediaId])]);
                }
                $api->sendImageById($to, $mediaId, $caption);
                break;

            case 'document_upload':
                $disk = (string) ($meta['document_disk'] ?? 'public');
                $path = (string) ($meta['document_upload_path'] ?? $meta['document_path'] ?? '');
                if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                    $sender->sendTextMessage($to, $intro ?: '');
                    break;
                }
                $caption = $locale === 'ar' ? ($meta['caption_ar'] ?? '') : ($meta['caption_en'] ?? '');

                $mediaId = $meta['wa_media_id'] ?? null;
                if (! $mediaId) {
                    $abs = Storage::disk($disk)->path($path);
                    $mime = @mime_content_type($abs) ?: null;
                    $mediaId = $api->uploadMedia($abs, $mime);
                    $t->update(['response_meta' => array_merge($meta, ['wa_media_id' => $mediaId])]);
                }
                $api->sendDocumentById($to, $mediaId, $caption);
                break;

            case 'buttons':
                $header = $locale === 'ar' ? ($meta['header_ar'] ?? '') : ($meta['header_en'] ?? '');
                $body = $locale === 'ar' ? ($meta['body_ar'] ?? $intro) : ($meta['body_en'] ?? $intro);

                $btns = array_map(function ($b) use ($locale) {
                    return [
                        'id' => (string) ($b['id'] ?? \Illuminate\Support\Str::uuid()),
                        'title' => (string) ($locale === 'ar' ? ($b['title_ar'] ?? $b['title_en'] ?? 'اختر') : ($b['title_en'] ?? 'Choose')),
                        'desc' => (string) ($locale === 'ar' ? ($b['desc_ar'] ?? '') : ($b['desc_en'] ?? '')),
                    ];
                }, array_values($meta['buttons'] ?? []));

                $sender->sendButtons($to, $body ?: '—', $btns, $header ?: null);
                break;

            case 'list':
                $header = $locale === 'ar' ? ($meta['header_ar'] ?? '') : ($meta['header_en'] ?? '');
                $body = $locale === 'ar' ? ($meta['body_ar'] ?? $intro) : ($meta['body_en'] ?? $intro);
                $btnLbl = $locale === 'ar' ? ($meta['button_label_ar'] ?? 'افتح') : ($meta['button_label_en'] ?? 'Open');
                $footer = $locale === 'ar' ? ($meta['footer_ar'] ?? '') : ($meta['footer_en'] ?? '');

                $sections = array_map(function ($sec) use ($locale) {
                    $title = $locale === 'ar' ? ($sec['title_ar'] ?? $sec['title_en'] ?? '') : ($sec['title_en'] ?? '');
                    $rows = array_map(function ($r) use ($locale) {
                        return [
                            'id' => (string) ($r['id'] ?? \Illuminate\Support\Str::uuid()),
                            'title' => (string) ($locale === 'ar' ? ($r['title_ar'] ?? $r['title_en'] ?? '—') : ($r['title_en'] ?? '—')),
                            'description' => (string) ($locale === 'ar' ? ($r['desc_ar'] ?? '') : ($r['desc_en'] ?? '')),
                        ];
                    }, array_values($sec['rows'] ?? []));

                    return ['title' => $title, 'rows' => $rows];
                }, array_values($meta['sections'] ?? []));

                $api->sendListRaw($to, $header, $body ?: '—', $btnLbl, $sections, $footer);
                break;

            case 'template':
                $tpl = (string) ($meta['template_name'] ?? '');
                if ($tpl === '') {
                    $sender->sendTextMessage($to, $intro ?: '');
                    break;
                }

                $lang = (string) ($meta['lang_override'] ?? ($locale === 'ar' ? 'ar' : 'en_US'));
                $bodyParams = $locale === 'ar'
                    ? (array) ($meta['body_params_ar'] ?? [])
                    : (array) ($meta['body_params_en'] ?? []);

                // Optional header image via sendTemplateRaw if provided
                if (! empty($meta['header_image_url'])) {
                    $api->sendTemplateRaw($to, $tpl, $lang,
                        /* header */ [['type' => 'image', 'image' => ['link' => (string) $meta['header_image_url']]]],
                        /* body */ $bodyParams,
                        /* btns */ []
                    );
                } else {
                    $sender->sendTemplate($to, $tpl, $lang, $bodyParams);
                }
                break;

            case 'flow':
                if ($intro !== '') {
                    $sender->sendTextMessage($to, $intro);
                }
                // Always open your configured, locale-based Flow (HOME)
                $this->ui->openFlowHome($session);
                break;

            case 'text':
            default:
                $sender->sendTextMessage($to, $intro ?: '');
                break;
        }
    }

    /* ----- Utilities ----- */

    private function sender(): \App\Services\WhatsAppSender
    {
        return new \App\Services\WhatsAppSender($this->waFactory->make());
    }

    private function lang($session): string
    {
        return ($session->locale === 'ar') ? 'ar' : 'en';
    }

    private function normalizeKeyword(string $text): string
    {
        $t = trim(mb_strtolower($text, 'UTF-8'));
        $t = preg_replace('/^[\s[:punct:]\x{2000}-\x{206F}\x{1F300}-\x{1FAFF}]+|[\s[:punct:]\x{2000}-\x{206F}\x{1F300}-\x{1FAFF}]+$/u', '', $t);
        $t = preg_replace('/\s+/u', ' ', $t);

        return $t ?: trim($text);
    }
}
