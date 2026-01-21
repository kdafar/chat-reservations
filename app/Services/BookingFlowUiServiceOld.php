<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\WhatsappSession;
use App\Support\Settings as Sys;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Encapsulates all WA UI concerns: Flow INIT, buttons/lists,
 * payload building, formatting, and small i18n helpers.
 */
class BookingFlowUiService
{
    public function __construct(
        protected WhatsAppApiServiceFactory $waFactory,
        protected AvailabilityService $availability,
        protected MessageCatalog $messages,
    ) {}

    /* ---------- PUBLIC UI ACTIONS ---------- */

    /** INIT the flow with data_exchange (backend INIT hook). */
    public function askFlowAppointment(WhatsappSession $session, array $overrides = []): void
    {
        $ctx = (array) $session->context;

        if (empty($ctx['branch_id'])) {
            $ctx['branch_id'] = $this->defaultBranchId();
        }

        // keep state + token
        $ctx['__state'] = 'FLOW';
        $ctx['flow_token'] = $ctx['flow_token'] ?? (string) Str::uuid();
        $this->saveCtx($session, $ctx);

        [$flowId, $cta] = $this->resolveFlowForLocale($session);

        // INIT with dynamic_object: {}
        $this->waFactory->make()->sendFlowAppointmentDataExchange(
            msisdn: $session->phone,
            flowId: $flowId,
            flowToken: $ctx['flow_token'],
            cta: $cta,
            mode: config('services.whatsapp.flows.mode', 'published'),
            version: '3',
        );
    }

    /** Re-open the flow (same INIT). */
    public function openAppointmentFlow(WhatsappSession $session): void
    {
        $this->askFlowAppointment($session);
    }

    /** Single active booking gate (buttons). */
    public function askActiveDecisionSingle(WhatsappSession $session, Booking $b): void
    {
        $locale = $this->lang($session);
        $branch = Branch::find($b->branch_id)?->getTranslation('name', $locale) ?? ('#'.$b->branch_id);

        $msg = $this->t($session, 'gate.active', [
            'branch' => $branch,
            'date' => $b->res_date,
            'time' => $b->res_time,
        ]);

        $this->sender()->sendButtons(
            $session->phone,
            $msg,
            [
                ['id' => 'change_existing', 'title' => $this->tp($session, 'btn.change_existing', 'Change existing')],
                ['id' => 'book_new',        'title' => $this->tp($session, 'btn.book_new', 'Book new')],
            ],
            $locale === 'ar' ? 'ماجستيك • بارفريز' : 'Majestic • Barfres'
        );

        $c = (array) $session->context;
        $c['__state'] = 'ACTIVE_DECISION';
        $c['active_booking_id'] = $b->id;
        unset($c['active_booking_ids']);
        $this->saveCtx($session, $c);
    }

    /** Multiple upcoming bookings gate (list). */
    public function askActiveDecisionMulti(WhatsappSession $session, Collection $bookings): void
    {
        $locale = $this->lang($session);

        $rows = [];
        foreach ($bookings as $b) {
            [$title, $desc] = $this->formatBookingShort($b, $locale);
            $rows[] = ['id' => 'pick_'.$b->id, 'label' => $title, 'desc' => $desc];
        }

        $this->sender()->sendListMessage(
            $session->phone,
            $this->tp($session, 'gate.multi.header', $locale === 'ar' ? 'حجوزاتك القادمة' : 'Your upcoming bookings'),
            $this->tp($session, 'gate.multi.body', $locale === 'ar' ? 'اختر حجزاً لإدارته:' : 'Pick a booking to manage:'),
            $this->tp($session, 'gate.multi.button', $locale === 'ar' ? 'عرض الحجوزات' : 'View bookings'),
            $rows,
            $this->tp($session, 'gate.multi.section', $locale === 'ar' ? 'حجوزاتي' : 'My bookings'),
            $this->tp($session, 'gate.multi.footer', $locale === 'ar' ? 'اختر واحداً' : 'Select one'),
        );

        $c = (array) $session->context;
        $c['__state'] = 'ACTIVE_DECISION';
        $c['active_booking_ids'] = $bookings->pluck('id')->values()->all();
        unset($c['active_booking_id']);
        $this->saveCtx($session, $c);
    }

    /** Change menu for a chosen booking. */
    public function askChangeWhat(WhatsappSession $session, int $bookingId): void
    {
        $this->sender()->sendListMessage(
            $session->phone,
            $this->tp($session, 'change.header', 'What would you like to change?'),
            $this->tp($session, 'change.body', 'Choose an option:'),
            $this->tp($session, 'change.button', 'Show options'),
            [
                ['id' => 'change_time',    'label' => $this->tp($session, 'change.time_label', 'Change time/date'),  'desc' => ''],
                ['id' => 'change_size',    'label' => $this->tp($session, 'change.size_label', 'Change party size'), 'desc' => ''],
                ['id' => 'cancel_booking', 'label' => $this->tp($session, 'change.cancel_label', 'Cancel booking'),  'desc' => ''],
                ['id' => 'back',           'label' => $this->tp($session, 'btn.back', 'Back'),                'desc' => ''],
            ],
            $this->tp($session, 'change.section', 'Options')
        );
    }

    /* ---------- BUILDERS / HELPERS ---------- */

    public function buildAppointmentPayload(WhatsappSession $session, array $ctx, array $overrides = []): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $locale = $this->lang($session);
        $branchId = (int) ($ctx['branch_id'] ?? $this->defaultBranchId());
        $forward = (int) config('booking.dates_forward_days', 60);

        $party = isset($ctx['party_size']) ? (int) $ctx['party_size'] : null;
        $resDate = (string) ($ctx['res_date'] ?? '');
        $resTime = (string) ($ctx['res_time'] ?? '');

        $includeDays = $this->includeDayNames($branchId);
        $minDate = now($tz)->toDateString();
        $maxDate = Carbon::parse($minDate, $tz)->addDays($forward)->toDateString();

        $payload = [
            'party_size' => $this->partyOptions($branchId, $locale),
            'party_size_prefill' => $party ? (string) $party : '',
            'is_date_enabled' => true,
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'unavailable_dates' => $this->blackoutDates($branchId),
            'include_days' => $includeDays,
            'time' => [],
            'is_time_enabled' => true,
            'show_time' => false,
            'confirmation_message' => '',
        ];

        if ($resDate !== '' && $party) {
            $slots = $this->availability->timesFor($branchId, $resDate, $party);
            $payload['time'] = collect($slots)->map(fn ($r) => [
                'id' => (string) $r['value'],
                'title' => $locale === 'ar' ? (string) $r['value'] : (string) $r['label'],
                'enabled' => true,
                'image' => self::tinyPixel(),
            ])->values()->all();
            $payload['show_time'] = ! empty($payload['time']);
        }

        return array_replace($payload, $overrides);
    }

    public function defaultBranchId(): int
    {
        $cfg = (int) (config('booking.default_branch_id') ?: 0);

        if ($cfg > 0) {
            return $cfg;
        }

        $single = Branch::where('is_available', true)->orderBy('id')->value('id');

        return (int) ($single ?: 1);
    }

    public function partyOptions(int $branchId, string $locale): array
    {
        return $locale === 'ar'
            ? [
                ['id' => '2', 'title' => 'شخصان',   'image' => self::tinyPixel()],
                ['id' => '4', 'title' => '٤ أشخاص', 'image' => self::tinyPixel()],
                ['id' => '6', 'title' => '٦ أشخاص', 'image' => self::tinyPixel()],
            ]
            : [
                ['id' => '2', 'title' => '2 people', 'image' => self::tinyPixel()],
                ['id' => '4', 'title' => '4 people', 'image' => self::tinyPixel()],
                ['id' => '6', 'title' => '6 people', 'image' => self::tinyPixel()],
            ];
    }

    public function includeDayNames(int $branchId): array
    {
        // keep simple here; your endpoint is authoritative
        return ['Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }

    public function blackoutDates(int $branchId): array
    {
        return [];
    }

    public function mapTimesFor(int $branchId, string $date, int $partySize, string $locale): array
    {
        $rows = $this->availability->timesFor($branchId, $date, $partySize);

        return collect($rows)->map(fn ($r) => [
            'id' => (string) $r['value'],
            'title' => $locale === 'ar' ? (string) $r['value'] : (string) $r['label'],
            'enabled' => true,
            'image' => self::tinyPixel(),
        ])->values()->all();
    }

    public static function tinyPixel(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2Z1mQAAAAASUVORK5CYII=';
    }

    public function resolveFlowForLocale(WhatsappSession $session): array
    {
        $locale = $this->lang($session);
        $flowId = $locale === 'ar'
            ? (config('services.whatsapp.flows.booking_id_ar') ?? env('WA_FLOW_BOOKING_ID_AR'))
            : (config('services.whatsapp.flows.booking_id_en') ?? env('WA_FLOW_BOOKING_ID_EN'));

        $cta = $locale === 'ar'
            ? (config('services.whatsapp.flows.cta_ar') ?? env('WA_FLOWS_CTA_AR', 'احجز الآن'))
            : (config('services.whatsapp.flows.cta') ?? env('WA_FLOWS_CTA_EN', 'Book now'));

        return [(string) $flowId, (string) $cta];
    }

    /* ---------- INTERNALS ---------- */

    protected function sender(): WhatsAppSender
    {
        return new WhatsAppSender($this->waFactory->make());
    }

    protected function saveCtx(WhatsappSession $session, array $ctx): void
    {
        $session->update(['context' => $ctx, 'last_interacted_at' => now()]);
    }

    protected function lang(WhatsappSession $s): string
    {
        return $s->locale === 'ar' ? 'ar' : 'en';
    }

    protected function t(WhatsappSession $s, string $key, array $vars = []): string
    {
        return app(MessageCatalog::class)->get($key, $this->lang($s), $vars);
    }

    protected function tp(WhatsappSession $s, string $key, string $fallback, array $vars = []): string
    {
        $text = $this->t($s, $key, $vars);

        return $text === $key ? $fallback : $text;
    }

    protected function truncate(string $t, int $max): string
    {
        return mb_strlen($t) > $max ? (mb_substr($t, 0, $max - 1).'…') : $t;
    }

    protected function formatBookingShort(Booking $b, string $locale): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $dt = $this->safeCreateDateTime(
            (string) $b->res_date,
            (string) $b->res_time,
            $tz
        )->locale($locale);

        $dateText = $dt->isoFormat($locale === 'ar' ? 'ddd D MMM' : 'ddd, MMM D');
        $timeText = $dt->isoFormat($locale === 'ar' ? 'h:mm a' : 'h:mm A');
        $branch = Branch::find($b->branch_id)?->getTranslation('name', $locale) ?? ('#'.$b->branch_id);

        $title = $this->truncate($locale === 'ar' ? "رمز {$b->booking_code}" : "Code {$b->booking_code}", 24);
        $desc = $this->truncate(
            $locale === 'ar'
                ? "{$branch} • {$dateText} • {$timeText} • {$b->party_size} أشخاص"
                : "{$branch} • {$dateText} • {$timeText} • {$b->party_size} ppl",
            72
        );

        return [$title, $desc];
    }

    private function safeCreateDateTime(string $resDate, string $resTime, string $tz): \Carbon\Carbon
    {
        $d = $this->normalizeDate($resDate, $tz);
        $t = $this->normalizeTime($resTime, $tz); // returns either HH:MM or HH:MM:SS

        $fmt = (strlen($t) === 8) ? 'Y-m-d H:i:s' : 'Y-m-d H:i';
        try {
            return \Carbon\Carbon::createFromFormat($fmt, "{$d} {$t}", $tz);
        } catch (\Throwable $e) {
            return \Carbon\Carbon::parse("{$d} {$t}", $tz);
        }
    }

    private function normalizeDate(string $resDate, string $tz): string
    {
        $resDate = trim($resDate);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $resDate)) {
            return substr($resDate, 0, 10);
        }

        return \Carbon\Carbon::parse($resDate, $tz)->toDateString();
    }

    private function normalizeTime(string $resTime, string $tz): string
    {
        $resTime = trim($resTime);
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $resTime)) {
            return substr($resTime, 0, 8);
        }
        if (preg_match('/^\d{2}:\d{2}$/', $resTime)) {
            return $resTime;
        }

        return \Carbon\Carbon::parse($resTime, $tz)->format('H:i');
    }

    public function openFlowHome(WhatsappSession $session): void
    {
        $locale = $session->locale === 'ar' ? 'ar' : 'en';

        // ensure flow_token
        $ctx = (array) ($session->context ?? []);
        $flowToken = (string) ($ctx['flow_token'] ?? Str::uuid());
        if (empty($ctx['flow_token'])) {
            $ctx['flow_token'] = $flowToken;
            $session->update(['context' => $ctx, 'last_interacted_at' => now()]);
        }

        $flowEnabled = (bool) (Sys::get('whatsapp.flow.enabled'));
        if ($flowEnabled) {
            $flowId = config('services.whatsapp.flows.booking_id_'.$locale);
            $cta = $locale === 'ar'
                ? (config('services.whatsapp.flows.cta_ar') ?? 'احجز الآن')
                : (config('services.whatsapp.flows.cta') ?? 'Book now');

            app(WhatsAppApiService::class)->sendFlowAppointmentDataExchange(
                msisdn: $session->phone,
                flowId: $flowId,
                flowToken: $flowToken,
                cta: $cta,
                locale: $locale,
                mode: config('services.whatsapp.flows.mode', 'published'),
                version: '3',
                name: $session->profile_name ?? $session->name
            );

            return;
        }

        $cooldown = (int) config('services.whatsapp.templates.cooldown_minutes', 60);
        $key = 'wa:welcome_closed:'.$session->phone;

        if (cache()->add($key, 1, now()->addMinutes($cooldown))) {
            $this->sendWelcomeClosedNotice($session);
        } else {
            $isAr = $session->locale === 'ar';
            $text = $isAr
            ? 'نحن مغلقون حالياً ولا نستقبل حجوزات عبر واتساب. يمكنك تصفّح القائمة للتعرّف علينا.'
            : 'We’re currently closed and not taking bookings on WhatsApp. You can browse the menu to learn more about us.';
            $this->sender()->sendTextMessage($session->phone, $text);
        }
    }

    private function sendWelcomeClosedNotice(WhatsappSession $session): void
    {
        $isAr = $session->locale === 'ar';

        $tplName = $isAr
            ? config('services.whatsapp.templates.welcome_name_ar', 'welcome')
            : config('services.whatsapp.templates.welcome_name_en', 'welcome');

        $langCode = $isAr
            ? config('services.whatsapp.templates.welcome_lang_ar', 'ar')
            : config('services.whatsapp.templates.welcome_lang_en', 'en');

        // Send the APPROVED welcome template (with Menu/Location/Working Hours buttons)
        $this->sender()->sendTemplate($session->phone, $tplName, $langCode, []);

        // Minimal follow-up note (no extra instructions)
        $text = $isAr
            ? 'نحن مغلقون حالياً ولا نستقبل حجوزات عبر واتساب. يمكنك تصفّح القائمة للتعرّف علينا.'
            : 'We’re currently closed and not taking bookings on WhatsApp. You can browse the menu to learn more about us.';
        $this->sender()->sendTextMessage($session->phone, $text);
    }
}
