<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Doctor; // Assumed model
use App\Models\Partner; // Assumed model
use App\Models\WhatsappSession;
use App\Support\Settings as Sys;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // Added Log for debugging

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

        // Reset selection context on new flow init if desired
        // unset($ctx['branch_id'], $ctx['doctor_id'], $ctx['clinic_id']);

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

    /* ---------- NEW PAYLOAD BUILDERS (New Flow) ---------- */

    public function buildHomePayload(WhatsappSession $session): array
    {
        $bookings = Booking::where('whatsapp_session_id', $session->id)
            ->where('res_date', '>=', now()->toDateString())
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();

        $name = $session->profile_name ?: ($session->name ?: 'Guest');

        return [
            'customer_name' => "Welcome, {$name}!",
            'welcome_message' => 'How can we help with your health today?',
            'has_bookings' => $bookings,
        ];
    }

    public function buildSelectBranchPayload(WhatsappSession $session, ?string $selectedClinicId = null): array
    {
        $locale = $this->lang($session);

        // 1. Load Clinics (Partners)
        // Ensure Partner model exists and has translation methods
        $clinicsQuery = Partner::query();
        // If your partner table has an 'is_active' flag, uncomment below:
        // $clinicsQuery->where('is_active', true);

        $clinics = $clinicsQuery->get()->map(function ($p) use ($locale) {
            // Handle name translation gracefully if getTranslation isn't available
            $name = $p->name;
            if (method_exists($p, 'getTranslation')) {
                $name = $p->getTranslation('name', $locale);
            } elseif (is_array($p->name)) {
                $name = $p->name[$locale] ?? $p->name['en'] ?? '';
            } elseif (is_string($p->name) && str_starts_with($p->name, '{')) {
                $json = json_decode($p->name, true);
                $name = $json[$locale] ?? $json['en'] ?? '';
            }

            return [
                'id' => (string) $p->id,
                'title' => (string) $name,
            ];
        })->values()->all();

        // 2. If Clinic Selected, Load Branches
        $branches = [];
        $showBranches = false;

        if ($selectedClinicId) {
            Log::info('Fetching branches for partner_id: '.$selectedClinicId);

            $branchesQuery = Branch::where('partner_id', $selectedClinicId)
                ->where('is_available', true);

            $branches = $branchesQuery->get()->map(function ($b) use ($locale) {
                // Name translation
                $name = $b->name;
                if (method_exists($b, 'getTranslation')) {
                    $name = $b->getTranslation('name', $locale);
                } elseif (is_array($b->name)) {
                    $name = $b->name[$locale] ?? $b->name['en'] ?? '';
                } elseif (is_string($b->name) && str_starts_with($b->name, '{')) {
                    $json = json_decode($b->name, true);
                    $name = $json[$locale] ?? $json['en'] ?? '';
                }

                // Address translation (description)
                $desc = '';
                if ($b->address) {
                    if (method_exists($b, 'getTranslation')) {
                        $desc = $b->getTranslation('address', $locale);
                    } elseif (is_array($b->address)) {
                        $desc = $b->address[$locale] ?? $b->address['en'] ?? '';
                    } elseif (is_string($b->address) && str_starts_with($b->address, '{')) {
                        $json = json_decode($b->address, true);
                        $desc = $json[$locale] ?? $json['en'] ?? '';
                    } else {
                        $desc = (string) $b->address;
                    }
                }

                return [
                    'id' => (string) $b->id,
                    'title' => (string) $name,
                    'description' => substr((string) $desc, 0, 60), // Limits description length
                ];
            })->values()->all();

            $showBranches = ! empty($branches);
            Log::info('Found '.count($branches).' branches.');
        }

        return [
            'clinics' => $clinics,
            'branches' => $branches,
            'show_branches' => $showBranches,
        ];
    }

    public function buildSelectDoctorPayload(WhatsappSession $session, int $branchId): array
    {
        $locale = $this->lang($session);

        // Fetch doctors for the selected branch
        // Ensure Doctor model exists and has correct relationships/columns
        $doctors = Doctor::where('branch_id', $branchId)
            ->where('is_active', true)
            ->get()
            ->map(function ($d) use ($locale) {
                $name = $d->name; // Simple name usually not translated, but adjust if needed

                // Specialty translation
                $desc = $d->specialty ?? '';
                if (method_exists($d, 'getTranslation')) {
                    $desc = $d->getTranslation('specialty', $locale);
                }

                return [
                    'id' => (string) $d->id,
                    'title' => (string) $name,
                    'description' => (string) $desc,
                ];
            })->values()->all();

        return [
            'doctors' => $doctors,
        ];
    }

    public function buildAppointmentPayload(WhatsappSession $session, array $ctx, array $overrides = []): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $locale = $this->lang($session);

        $branchId = (int) ($ctx['branch_id'] ?? $this->defaultBranchId());
        $doctorId = isset($ctx['doctor_id']) ? (int) $ctx['doctor_id'] : null;

        $forward = (int) config('booking.dates_forward_days', 60);

        $party = isset($ctx['party_size']) ? (string) $ctx['party_size'] : null;
        $resDate = (string) ($ctx['res_date'] ?? '');

        // Logic for min/max date
        $minDate = now($tz)->toDateString();
        $maxDate = Carbon::parse($minDate, $tz)->addDays($forward)->toDateString();

        // Prepare party sizes (types of appointment)
        $partyOptions = $this->partyOptions($branchId, $locale);

        $payload = [
            'available_party_sizes' => $partyOptions,
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'unavailable_dates' => $this->blackoutDates($branchId),
            'available_times' => [],
            'show_times' => false,
        ];

        // Fetch slots if Date and Party Size (Type) are selected
        if ($resDate !== '' && $party) {
            // Updated to pass doctorId if your AvailabilityService supports it
            // Assuming AvailabilityService::timesFor signature is (branchId, date, partySize, doctorId)
            // Passing 1 as partySize integer for slot logic, since $party is now a type string
            $partyInt = 1;
            $slots = $this->availability->timesFor($branchId, $resDate, $partyInt, $doctorId);

            $payload['available_times'] = collect($slots)->map(fn ($r) => [
                'id' => (string) $r['value'],
                'title' => $locale === 'ar' ? (string) $r['value'] : (string) $r['label'],
                'enabled' => true,
            ])->values()->all();

            $payload['show_times'] = ! empty($payload['available_times']);
        }

        return array_replace($payload, $overrides);
    }

    /* ---------- HELPERS ---------- */

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
        // Now using id/title format for Dropdown/Radio in Flow 3.0
        return $locale === 'ar'
            ? [
                ['id' => 'general', 'title' => 'كشف عام'],
                ['id' => 'specialist', 'title' => 'استشارة مختص'],
                ['id' => 'followup', 'title' => 'مراجعة'],
            ]
            : [
                ['id' => 'general', 'title' => 'General Consultation'],
                ['id' => 'specialist', 'title' => 'Specialist Consultation'],
                ['id' => 'followup', 'title' => 'Follow-up'],
            ];
    }

    public function includeDayNames(int $branchId): array
    {
        return ['Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    }

    public function blackoutDates(int $branchId): array
    {
        return []; // Implement actual logic
    }

    // ... [Helpers: sender, saveCtx, lang, t, tp, truncate, formatBookingShort, safeCreateDateTime]
    // Standard helpers kept below for brevity, assuming they remain unchanged.

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
        $t = $this->normalizeTime($resTime, $tz);

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
