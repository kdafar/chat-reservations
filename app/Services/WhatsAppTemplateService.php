<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\WhatsappSession;
use Carbon\Carbon;
use Illuminate\Support\Str;

class WhatsAppTemplateService
{
    public function __construct(protected WhatsAppSender $sender) {}

    /** Helper: map app locale → Graph template language code */
    private function langCode(?string $locale): string
    {
        return ($locale === 'ar') ? 'ar' : 'en_US';
    }

    /** Helper: localized date/time strings used by templates */
    private function buildLocalDateTime(?string $date, ?string $time, string $tz = 'Asia/Kuwait'): ?Carbon
    {
        $date = trim((string) $date);
        $time = trim((string) $time);
        if ($date === '' || $time === '') {
            \Log::warning('BookingFlow: missing date/time', compact('date', 'time'));

            return null;
        }

        // Normalize date/time first
        $date = str_replace('/', '-', $date);
        $date = Str::of($date)->before(' ')->value();       // <-- strip any time from date

        // normalize time; if it contains date, strip it
        $timeOnly = Str::of($time)->after(' ')->value();    // <-- strip any date from time
        $timeOnly = $timeOnly !== '' ? $timeOnly : $time;

        // ensure seconds if missing
        if (preg_match('/^\d{2}:\d{2}$/', $timeOnly)) {
            $timeOnly .= ':00';
        }

        $combo = "{$date} {$timeOnly}";
        $candidates = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d g:i:s A', 'Y-m-d g:i A'];

        foreach ($candidates as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $combo, $tz);
                if ($dt !== false) {
                    return $dt;
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($combo, $tz);
        } catch (\Throwable $e) {
            \Log::error('BookingFlow: failed to parse date/time', [
                'date' => $date, 'time' => $time, 'combo' => $combo, 'err' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function fmtDateTime(Booking $b, string $locale, string $tz): array
    {
        $dt = $this->buildLocalDateTime($b->res_date, $b->res_time, $tz);
        if ($dt) {
            $dt = $dt->locale($locale);
            $dateText = $dt->isoFormat($locale === 'ar' ? 'ddd D MMM' : 'ddd, MMM D');
            $timeText = $dt->isoFormat($locale === 'ar' ? 'h:mm a' : 'h:mm A');
        } else {
            $dateText = (string) $b->res_date;
            $timeText = (string) $b->res_time;
        }

        \Log::info('Template fmtDateTime', [
            'tz' => $tz,
            'res_date' => $b->res_date,
            'res_time' => $b->res_time,
            'rendered_date' => $dateText,
            'rendered_time' => $timeText,
            'locale' => $locale,
        ]);

        return [$dateText, $timeText];
    }

    /** Booking confirmed (ar/en) */
    public function bookingConfirmed(WhatsappSession $session, Booking $b): bool
    {
        $locale = $session->locale === 'ar' ? 'ar' : 'en';
        $lang = $this->langCode($locale);
        $tz = config('app.timezone', 'Asia/Kuwait');

        [$dateText, $timeText] = $this->fmtDateTime($b, $locale, $tz);

        if ($locale === 'ar') {
            $branchName = \App\Models\Branch::find($b->branch_id)?->getTranslation('name', 'ar') ?? '';

            return $this->sender->sendTemplate(
                $session->phone, 'barfres_confirmed', $lang,
                [(string) $b->party_size, $dateText, $timeText, $branchName, (string) $b->booking_code]
            );
        }

        // booking_confirmed_final expects: size, date, time
        return $this->sender->sendTemplate(
            $session->phone, 'booking_confirmed_final', $lang,
            [(string) $b->party_size, $dateText, $timeText]
        );
    }

    /** 24h reminder (ar/en) */
    public function reminder24h(WhatsappSession $session, Booking $b): bool
    {
        $locale = $session->locale === 'ar' ? 'ar' : 'en';
        $lang = $this->langCode($locale);
        $tz = config('app.timezone', 'Asia/Kuwait');
        [$dateText, $timeText] = $this->fmtDateTime($b, $locale, $tz);

        $branchName = Branch::find($b->branch_id)?->getTranslation('name', $locale) ?? '';

        if ($locale === 'ar') {
            // barfres_reminder_24h (ar) expects: size, date, time, branch, code
            return $this->sender->sendTemplate(
                $session->phone,
                'barfres_reminder_24h',
                $lang,
                [
                    (string) $b->party_size,
                    (string) $dateText,
                    (string) $timeText,
                    (string) $branchName,
                    (string) $b->booking_code,
                ]
            );
        }

        // barfres_reminder_24h (en) expects: size, date, time, branch, code
        return $this->sender->sendTemplate(
            $session->phone,
            'barfres_reminder_24h',
            $lang,
            [
                (string) $b->party_size,
                (string) $dateText,
                (string) $timeText,
                (string) $branchName,
                (string) $b->booking_code,
            ]
        );
    }

    /** No slots (ar/en) */
    public function noSlots(string $to, string $locale, string $branchName, string $dateText, string $partySize): bool
    {
        $lang = $this->langCode($locale);
        $tpl = $locale === 'ar' ? 'barfres_no_slots' : 'barfres_no_slots';

        // ar expects: branch, date, party ; en expects: branch, date, party
        return $this->sender->sendTemplate(
            $to,
            $tpl,
            $lang,
            [(string) $branchName, (string) $dateText, (string) $partySize]
        );
    }

    /** Out of hours (en) */
    public function outOfHours(string $to, string $branchName, string $openTime, string $closeTime): bool
    {
        return $this->sender->sendTemplate(
            $to,
            'barfres_out_of_hours',
            'en_US',
            [(string) $branchName, (string) $openTime, (string) $closeTime]
        );
    }

    /** Hold lost (en) */
    public function holdLost(string $to, string $branchName, string $dateText): bool
    {
        return $this->sender->sendTemplate(
            $to,
            'barfres_hold_lost',
            'en_US',
            [(string) $branchName, (string) $dateText]
        );
    }

    /** Review confirm (en) – “booking_review_confirm” */
    public function reviewConfirmEN(string $to, string $party, string $dateText, string $timeText): bool
    {
        return $this->sender->sendTemplate(
            $to,
            'booking_review_confirm',
            'en_US',
            [(string) $party, (string) $dateText, (string) $timeText]
        );
    }

    /** Review 1 (en) – “barfres_review_1” */
    public function review1EN(string $to, string $branch, string $party, string $dateText, string $timeText): bool
    {
        return $this->sender->sendTemplate(
            $to,
            'barfres_review_1',
            'en_US',
            [(string) $branch, (string) $party, (string) $dateText, (string) $timeText]
        );
    }

    /** Session expired (ar/en) – no variables */
    public function sessionExpired(string $to, string $locale): bool
    {
        $lang = $this->langCode($locale);
        $tpl = $locale === 'ar' ? 'barfres_session_expired' : 'barfres_session_expired';

        return $this->sender->sendTemplate($to, $tpl, $lang, []);
    }

    /** Marketing invite (ar/en) – optional image header link from config */
    public function invite(string $to, string $locale): bool
    {
        $lang = $this->langCode($locale);
        $tpl = 'barfres_invite';

        $img = $locale === 'ar'
            ? (config('services.whatsapp.templates.invite_header_ar') ?? null)
            : (config('services.whatsapp.templates.invite_header_en') ?? null);

        if ($img) {
            // HEADER media param (Graph spec)
            $header = [['type' => 'image', 'image' => ['link' => $img]]];

            return $this->sender->sendTemplateAdvanced(
                $to,
                $tpl,
                $lang,
                $header,   // <-- header image now applied
                [],       // body has no placeholders in your approved version
                []         // buttons (none for now)
            );
        }

        // Fallback: body-only template (no header)
        return $this->sender->sendTemplate($to, $tpl, $lang, []);
    }
}
