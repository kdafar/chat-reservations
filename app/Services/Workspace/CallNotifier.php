<?php

namespace App\Services\Workspace;

use App\Models\Visit;
use App\Models\WhatsappSession;
use App\Support\Phone;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\WhatsappSession as WaSession;
use App\Wa\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tells a patient on WhatsApp that it is their turn: "please go to <room>".
 *
 * Sends the Meta template `<WHATSAPP_TEMPLATE_PREFIX>_queue_call` (or the
 * WHATSAPP_TEMPLATE_QUEUE_CALL override) through the same sender the visit
 * payment link uses, synchronously like it. Body variables, en and ar:
 *   {{1}} patient first name   {{2}} clinic name (config/tenant.php)   {{3}} room
 *
 * OFF unless WHATSAPP_QUEUE_CALL_ENABLED=true. Never throws — a failed notice
 * must never break calling the patient in.
 */
class CallNotifier
{
    /** Seconds within which a repeat call for the same visit + room is ignored (double-clicks). */
    private const DEDUPE_SECONDS = 30;

    /** @return array{sent: bool, reason: string|null} */
    public function notify(Visit $visit, string $room): array
    {
        try {
            return $this->attempt($visit, $room);
        } catch (\Throwable $e) {
            Log::warning('[QueueCall] WhatsApp call notice failed', [
                'visit_id' => $visit->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'error'];
        }
    }

    /** @return array{sent: bool, reason: string|null} */
    private function attempt(Visit $visit, string $room): array
    {
        if (! config('services.whatsapp.queue_call_enabled', false)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        if (blank(config('services.whatsapp.api_token')) || blank(config('services.whatsapp.phone_number_id'))) {
            return ['sent' => false, 'reason' => 'whatsapp_not_configured'];
        }

        $rawPhone = (string) ($visit->patient?->phone ?? $visit->booking?->msisdn ?? '');
        if (trim($rawPhone) === '') {
            return ['sent' => false, 'reason' => 'no_phone'];
        }

        // Patient phones are often stored local (8 digits); Meta needs the
        // country code. Same parser the rest of the app uses.
        $e164 = Phone::parseToE164AcrossRegions($rawPhone);
        if (! $e164) {
            return ['sent' => false, 'reason' => 'invalid_phone'];
        }
        $to = ltrim($e164, '+');

        $locale = $this->locale($to, $rawPhone);
        $template = $this->templateName();

        if (! $this->templateApproved($template, $locale)) {
            Log::warning('[QueueCall] Template not approved on Meta; skipping', [
                'visit_id' => $visit->id, 'template' => $template, 'locale' => $locale,
            ]);

            return ['sent' => false, 'reason' => 'template_not_approved'];
        }

        $room = trim($room);
        if ($room === '') {
            $room = $locale === 'ar' ? 'غرفة الطبيب' : "the doctor's room";
        }

        $dedupeKey = 'wa:queue_call:'.$visit->id.':'.md5($room);
        if (! Cache::add($dedupeKey, 1, self::DEDUPE_SECONDS)) {
            return ['sent' => false, 'reason' => 'duplicate'];
        }

        $res = app(WhatsAppService::class)->sendTemplate($to, [
            'name' => $template,
            'language' => ['code' => $locale],
            'components' => [[
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $this->firstName($visit, $locale)], // {{1}}
                    ['type' => 'text', 'text' => $this->clinicName($locale)],        // {{2}}
                    ['type' => 'text', 'text' => $room],                             // {{3}}
                ],
            ]],
        ], auth()->id());

        if (! data_get($res, 'messages.0.id')) {
            Cache::forget($dedupeKey); // let a retry through

            Log::warning('[QueueCall] WhatsApp send returned no message id', [
                'visit_id' => $visit->id, 'template' => $template,
            ]);

            return ['sent' => false, 'reason' => 'send_failed'];
        }

        return ['sent' => true, 'reason' => null];
    }

    /** Same resolution as WhatsAppTemplateService::tpl(): override, else "<prefix>_queue_call". */
    private function templateName(): string
    {
        $override = config('services.whatsapp.templates.queue_call');
        if (filled($override)) {
            return (string) $override;
        }

        return config('services.whatsapp.templates.prefix', 'barfres').'_queue_call';
    }

    /**
     * Patient's WhatsApp conversation language if we have one (the WA module's
     * session, then the booking bot's), else the app locale.
     */
    private function locale(string $toDigits, string $rawPhone): string
    {
        $local = preg_replace('/\D+/', '', $rawPhone);
        $candidates = array_values(array_unique(array_filter([$toDigits, '+'.$toDigits, $local])));

        $sessionLocale = null;
        foreach ([
            fn () => WaSession::query()->whereIn('customer_phone_number', $candidates)->latest('updated_at')->value('locale'),
            fn () => WhatsappSession::query()->whereIn('phone', $candidates)->latest('updated_at')->value('locale'),
        ] as $lookup) {
            try {
                $sessionLocale = $lookup();
            } catch (\Throwable) {
                $sessionLocale = null; // table/column absent on this install — fall through
            }
            if (filled($sessionLocale)) {
                break;
            }
        }

        $locale = $sessionLocale ?: app()->getLocale() ?: config('services.whatsapp.default_locale', 'en');

        return str_starts_with((string) $locale, 'ar') ? 'ar' : 'en';
    }

    /** Mirrors the payment-link check: only send what Meta has approved (local mirror of Meta status). */
    private function templateApproved(string $name, string $locale): bool
    {
        return MessageTemplate::query()
            ->where('name', $name)
            ->where('status', 'APPROVED')
            ->where(fn ($q) => $q->where('language', $locale)->orWhereNull('language'))
            ->exists();
    }

    private function firstName(Visit $visit, string $locale): string
    {
        $first = strtok(trim((string) ($visit->patient?->name ?? '')), " \t");

        return ($first !== false && $first !== '') ? $first : ($locale === 'ar' ? 'عميلنا' : 'there');
    }

    private function clinicName(string $locale): string
    {
        $name = config("tenant.name.{$locale}") ?: config('tenant.name.en') ?: config('app.name');

        return filled($name) ? (string) $name : ($locale === 'ar' ? 'العيادة' : 'the clinic');
    }
}
