<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function afterCreate(): void
    {
        /** @var Booking $b */
        $b = $this->record;

        // Only send for confirmed bookings created from admin side.
        if (($b->status ?? null) !== BookingResource::STATUS_CONFIRMED) {
            return;
        }

        // Do not send if no number.
        $msisdn = preg_replace('/\D+/', '', (string) ($b->msisdn ?? ''));
        if (! $msisdn) {
            return;
        }

        // Ensure QR token exists (same as your Resend action).
        app(\App\Services\QrPassService::class)->ensureToken($b);

        $qrUrl = route('bookings.qr', ['token' => $b->qr_token]);
        $passUrl = app(\App\Services\QrPassService::class)->passUrl($b);

        $tz = config('app.timezone', 'Asia/Kuwait');

        // IMPORTANT: resolveSlot() is protected in BookingResource in your current code.
        // So we compute start/end here without calling it.
        $start = $b->res_start;
        $end = $b->res_end;

        if (! $start) {
            $dateRaw = $b->res_date;
            $datePart = ($dateRaw instanceof \DateTimeInterface)
                ? $dateRaw->format('Y-m-d')
                : (explode(' ', trim((string) $dateRaw))[0] ?? '');

            $timeRaw = $b->res_time;
            $timePart = ($timeRaw instanceof \DateTimeInterface)
                ? $timeRaw->format('H:i:s')
                : trim((string) $timeRaw);

            if ($datePart !== '' && $timePart !== '') {
                try {
                    $start = \Carbon\Carbon::parse("$datePart $timePart", $tz)->seconds(0);
                } catch (\Throwable) {
                    $start = null;
                }
            }
        }

        if (! $end && $start) {
            $rule = \App\Models\BranchAvailabilityRule::where('branch_id', $b->branch_id)
                ->where('day_of_week', (int) $start->format('w'))
                ->first();

            $len = (int) ($rule?->slot_length_minutes ?? $rule?->slot_step_minutes ?? config('booking.slot_interval', 30));
            $len = max(5, $len);

            $end = $start->copy()->addMinutes($len)->seconds(0);
        }

        if ($start) {
            $start = $start->timezone($tz);
        }
        if ($end) {
            $end = $end->timezone($tz);
        }

        // Template-friendly values
        $dateTpl = $start ? $start->isoFormat('ddd, D MMM YYYY') : '—'; // {{1}}
        $timeTpl = $start ? $start->format('H:i') : '—';               // {{2}}

        // Legacy caption values
        $date = $start ? $start->isoFormat('ddd, D MMM') : '—';
        $time = $start
            ? $start->format('h:i A').($end ? '–'.$end->format('h:i A') : '')
            : '—';

        $code = (string) ($b->booking_code ?? '');
        $text = "Appointment Confirmed\nCode: {$code}\nDate: {$date}\nTime: {$time}\n\nYour QR Pass:\n{$passUrl}";

        $wa = app(\App\Services\WhatsAppSender::class);

        // Locale selection (safe fallback)
        $locale = app()->getLocale();
        $lang = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';

        // Optional override to force a specific language (e.g. force 'en' utility template)
        $forcedLang = (string) config('services.whatsapp.confirm_lang', '');
        if ($forcedLang !== '' && in_array($forcedLang, ['ar', 'en'], true)) {
            $lang = $forcedLang;
        }

        $sent = false;

        // 1) Template first (works outside 24h window; prevents WA error 131047)
        try {
            // Requires add-only method WhatsAppSender::sendClinicConfirmedV3()
            $sent = $wa->sendClinicConfirmedV3(
                $msisdn,
                $lang,
                $qrUrl,    // header image (dynamic QR)
                $dateTpl,  // {{1}}
                $timeTpl,  // {{2}}
                $code,     // {{3}}
                $passUrl   // {{4}}
            );
        } catch (\Throwable) {
            $sent = false;
        }

        // 2) Fallback: legacy image+caption (may fail outside 24h)
        if (! $sent) {
            try {
                $sent = $wa->sendImage($msisdn, $qrUrl, $text);
            } catch (\Throwable) {
                $sent = false;
            }
        }

        // 3) Final fallback: plain text
        if (! $sent) {
            try {
                $wa->sendTextMessage($msisdn, $text);
                $sent = true;
            } catch (\Throwable) {
                $sent = false;
            }
        }

        Notification::make()
            ->title($sent ? 'WhatsApp confirmation sent' : 'WhatsApp confirmation failed')
            ->success()
            ->send();
    }
}
