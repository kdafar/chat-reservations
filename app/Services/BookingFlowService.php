<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\WhatsappSession;
use App\Models\WhatsappTrigger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingFlowService
{
    public function __construct(
        protected WhatsAppApiServiceFactory $waFactory,
        protected MessageCatalog $messages,
        protected WhatsAppTemplateService $tpl,
        protected QrPassService $qr,
        protected BookingFlowUiService $ui, // Handles opening the Flow
        protected WhatsappTriggerService $triggers,
    ) {}

    /**
     * Main handler for Flow Data Exchange requests (INIT and interaction).
     * Called by the Controller receiving the decrypted Flow JSON.
     */
    public function processFlowRequest(array $request, WhatsappSession $session): array
    {
        $screen = $request['screen'] ?? 'INIT';
        $data = $request['data'] ?? [];
        // 'trigger' might come from the payload directly in some versions or inside data
        $trigger = $data['trigger'] ?? ($request['trigger'] ?? null);

        Log::info("Flow processing: {$screen}", ['trigger' => $trigger]);

        // Merge incoming flow data into session context for state persistence
        // We do this globally, but specific steps will perform hygiene (clearing) below.
        if (! empty($data)) {
            $currentCtx = $session->context ?? [];
            $newCtx = array_merge($currentCtx, $data);
            $session->update(['context' => $newCtx]);
        }

        switch ($screen) {
            case 'INIT':
                return [
                    'screen' => 'HOME',
                    'data' => $this->ui->buildHomePayload($session),
                ];

            case 'HOME':
                $choice = $data['home_choice'] ?? '';
                if ($choice === 'new_booking') {
                    // CRITICAL: Clear all booking state for a fresh start.
                    // This prevents "stuck" states from previous abandoned bookings.
                    $ctx = $session->context ?? [];
                    $keysToClear = ['clinic_id', 'branch_id', 'doctor_id', 'res_date', 'res_time', 'party_size', 'notes'];
                    foreach ($keysToClear as $k) {
                        unset($ctx[$k]);
                    }
                    $session->update(['context' => $ctx]);

                    // Start the new hierarchical flow: HOME -> SELECT_BRANCH
                    return [
                        'screen' => 'SELECT_BRANCH',
                        'data' => $this->ui->buildSelectBranchPayload($session),
                    ];
                }
                if ($choice === 'view_bookings') {
                    // Placeholder logic for viewing bookings
                    $bookings = Booking::where('msisdn', $session->phone)
                        ->where('res_date', '>=', now()->toDateString())
                        ->whereIn('status', ['confirmed', 'held'])
                        ->orderBy('res_date')
                        ->limit(10)
                        ->get()
                        ->map(fn ($b) => [
                            'id' => (string) $b->id,
                            'title' => $b->res_date.' '.$b->res_time,
                            'description' => 'Code: '.$b->booking_code,
                        ])->values()->all();

                    // Safety: Fallback if no bookings
                    if ($bookings === []) {
                        return [
                            'screen' => 'HOME',
                            'data' => $this->ui->buildHomePayload($session),
                        ];
                    }

                    return [
                        'screen' => 'BOOKINGS',
                        'data' => ['bookings' => $bookings],
                    ];
                }
                break;

            case 'SELECT_BRANCH':
                $clinicId = $data['clinic_id'] ?? null;

                if ($trigger === 'clinic_selected') {
                    // STATE HYGIENE: Clinic changed? Clear Branch & Doctor to avoid ID mismatches.
                    $ctx = $session->context ?? [];
                    unset($ctx['branch_id'], $ctx['doctor_id']);
                    // Ensure clinic is set
                    $ctx['clinic_id'] = $clinicId;
                    $session->update(['context' => $ctx]);

                    // Reload same screen but with branches visible
                    return [
                        'screen' => 'SELECT_BRANCH',
                        'data' => $this->ui->buildSelectBranchPayload($session, $clinicId),
                    ];
                }

                if ($trigger === 'branch_selected') {
                    $branchId = (int) ($data['branch_id'] ?? 0);

                    // STATE HYGIENE: Branch changed? Clear Doctor.
                    $ctx = $session->context ?? [];
                    unset($ctx['doctor_id']);
                    $ctx['branch_id'] = $branchId;
                    $session->update(['context' => $ctx]);

                    // Valid route: SELECT_BRANCH -> SELECT_DOCTOR
                    return [
                        'screen' => 'SELECT_DOCTOR',
                        'data' => $this->ui->buildSelectDoctorPayload($session, $branchId),
                    ];
                }
                break;

            case 'SELECT_DOCTOR':
                if ($trigger === 'doctor_selected') {
                    $rawDoc = $data['doctor_id'] ?? 0;
                    $doctorId = ($rawDoc === 'any') ? null : (int) $rawDoc;

                    // Explicitly save doctor to context
                    $ctx = array_merge($session->context ?? [], ['doctor_id' => $doctorId]);
                    $session->update(['context' => $ctx]);

                    // Valid route: SELECT_DOCTOR -> APPOINTMENT
                    return [
                        'screen' => 'APPOINTMENT',
                        'data' => $this->ui->buildAppointmentPayload($session, $ctx),
                    ];
                }
                break;

            case 'APPOINTMENT':
                // Handle dynamic updates (Date/Party Size change)
                if (in_array($trigger, ['party_size_selected', 'date_selected'])) {
                    // Logic: Update context with new selections so payload builder sees them
                    $ctx = array_merge($session->context ?? [], [
                        'party_size' => $data['party_size'] ?? ($session->context['party_size'] ?? 'general'),
                        'res_date' => $data['date'] ?? ($session->context['res_date'] ?? ''),
                    ]);
                    $session->update(['context' => $ctx]);

                    return [
                        'screen' => 'APPOINTMENT',
                        'data' => $this->ui->buildAppointmentPayload($session, $ctx),
                    ];
                }

                if ($trigger === 'continue_to_details') {
                    // Prepare summary text
                    $date = $data['date'] ?? '';
                    $time = $data['time'] ?? '';
                    $party = $data['party_size'] ?? 'general';
                    $branch = Branch::find($session->context['branch_id'] ?? 0)?->name ?? 'Clinic';

                    return [
                        'screen' => 'DETAILS',
                        'data' => [
                            'party_size' => $party,
                            'date' => $date,
                            'time' => $time,
                            'prefill_name' => $session->profile_name ?? '',
                            'prefill_phone' => $session->phone,
                            'booking_summary_text' => "Appointment at {$branch}\nDate: {$date}\nTime: {$time}\nType: {$party}",
                        ],
                    ];
                }
                break;

            case 'DETAILS':
                if ($trigger === 'continue_to_summary') {
                    // Pass all accumulated data to Summary
                    return [
                        'screen' => 'SUMMARY',
                        'data' => array_merge($data, [
                            'reservation_details_text' => "Date: {$data['date']}\nTime: {$data['time']}\nType: {$data['party_size']}",
                            'contact_details_text' => "Name: {$data['name']}\nPhone: {$data['phone']}\nEmail: ".($data['email'] ?? '-'),
                            'terms_required' => true,
                            'terms_label' => 'I agree to the clinic policies',
                            'terms_text' => '• Please arrive 15 min early.',
                        ]),
                    ];
                }
                break;

            case 'SUMMARY':
                if ($trigger === 'confirm_booking') {
                    // Create the actual booking
                    $booking = $this->createBookingFromFlow($session, $data);

                    return [
                        'screen' => 'CONFIRMATION',
                        'data' => [
                            'booking_id' => (string) $booking->id,
                            'booking_code' => $booking->booking_code,
                            'confirmation_heading' => '🎉 Confirmed!',
                            'confirmation_subheading' => 'Your appointment is set.',
                            'confirmation_details_text' => "Ref: {$booking->booking_code}\nDate: {$booking->res_date}\nTime: {$booking->res_time}",
                            'show_details' => true,
                            'end_session' => true,
                        ],
                    ];
                }
                break;
        }

        // Fallback to HOME
        return [
            'screen' => 'HOME',
            'data' => $this->ui->buildHomePayload($session),
        ];
    }

    protected function createBookingFromFlow(WhatsappSession $session, array $data): Booking
    {
        $branchId = $session->context['branch_id'] ?? $this->ui->defaultBranchId();

        $b = new Booking;
        $b->whatsapp_session_id = $session->id;
        $b->branch_id = $branchId;
        $b->doctor_id = $session->context['doctor_id'] ?? null;
        $b->res_date = $data['date'] ?? now()->toDateString();
        $b->res_time = $data['time'] ?? '09:00:00';

        // Handle party_size: Input comes as string (e.g., 'dental'), legacy DB might expect int.
        // Default to 1 if not numeric. Ideally, save the type in a separate column or notes.
        $rawParty = $data['party_size'] ?? 1;
        $b->party_size = is_numeric($rawParty) ? (int) $rawParty : 1;

        $b->customer_name = $data['name'] ?? 'Guest';
        $b->msisdn = $data['phone'] ?? $session->phone;
        $b->email = $data['email'] ?? null;

        // Append type to notes if it's text
        $notes = $data['notes'] ?? '';
        if (! is_numeric($rawParty)) {
            $notes .= " [Type: {$rawParty}]";
        }
        $b->notes = trim($notes) ?: null;

        $b->booking_code = strtoupper(Str::random(6));
        $b->status = 'confirmed';
        $b->save();

        return $b;
    }

    /**
     * Handle every inbound WhatsApp message:
     * - mark as read
     * - ensure session + locale
     * - handle Flow finale (QR + template + optional DB finale trigger)
     * - otherwise respond via triggers (welcome/keyword/fallback)
     * - then (re)open the Flow at HOME
     */
    public function handle(array $payload): void
    {
        $rid = (string) Str::uuid();
        Log::withContext(['rid' => $rid, 'scope' => 'wa.chat']);
        Log::info('WA chat: incoming', ['has_entry' => isset($payload['entry'][0]['changes'][0]['value'])]);

        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $msg = $value['messages'][0] ?? null;
        $from = $msg['from'] ?? null;

        if (! $from || ! $msg) {
            Log::warning('WA chat: no message or from');

            return;
        }

        // mark read
        if (! empty($msg['id'])) {
            $this->sender()->markAsRead($msg['id']);
        }

        // session
        $session = WhatsappSession::firstOrCreate(
            ['phone' => $from],
            ['status' => 'active', 'locale' => 'en', 'context' => []],
        );

        // locale (simple inference: ar if Arabic chars present; else keep previous)
        $text = $msg['text']['body'] ?? null;
        $detected = $this->inferLocale($text);
        if (empty($session->locale) && $detected) {
            $session->update(['locale' => $detected]);
        } elseif ($detected && $detected !== $session->locale) {
            $session->update(['locale' => $detected]);
        }
        $locale = $session->locale === 'ar' ? 'ar' : 'en';

        // Finale from Flow?
        if (isset($msg['interactive']['nfm_reply'])) {
            $meta = json_decode($msg['interactive']['nfm_reply']['response_json'] ?? '{}', true) ?: [];
            $this->onFlowFinale($session, $meta);

            return;
        }

        /* ---------------- TRIGGERS INTEGRATION ---------------- */

        // 1) Welcome (first time only)
        $ctx = (array) ($session->context ?? []);
        if (empty($ctx['welcomed'])) {
            if ($welcome = $this->findTrigger('welcome')) {
                $this->sendTrigger($welcome, $session);
            }

            $ctx['welcomed'] = true;
            $session->update(['context' => $ctx, 'last_interacted_at' => now()]);

            // optional: immediately show booking button/flow home
            $this->ui->openFlowHome($session);

            return;
        }

        if ($this->maybeHandleWorkingHoursQuickReply($payload, $session)) {
            return;
        }

        // 2) Keyword trigger (exact match on 'keyword' column when the user typed text)
        $text = $msg['text']['body'] ?? null;
        if (is_string($text) && trim($text) !== '') {

            $normalized = $this->normalizeKeyword($text);

            // Booking intent => open Flow
            if (in_array($normalized, ['book', 'booking', 'reserve', 'reservation', 'حجز', 'احجز', 'موعد'], true)) {
                $this->ui->openFlowHome($session);

                return;
            }

            // Keyword triggers (menu/about/location/etc.)
            $matched = $this->triggers->handleKeywordTrigger($session, $normalized);
            if ($matched) {
                return;
            }

            // Fallback => just re-open Flow home
            $this->ui->openFlowHome($session);

            return;
        }

        // Anything else: open (or reopen) the Flow at HOME
        $this->ui->openFlowHome($session); // make sure your UI service targets screen=HOME
    }

    /* ---------------- finale + confirmation ---------------- */

    protected function onFlowFinale(WhatsappSession $session, array $nfm = []): void
    {
        $id = $nfm['booking_id'] ?? null;
        $code = $nfm['booking_code'] ?? null;
        $endSession = filter_var($nfm['end_session'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($endSession) {
            return;
        }

        $b = null;
        if ($id) {
            $b = Booking::find($id);
        } elseif ($code) {
            $b = Booking::where('booking_code', $code)->first();
        } else {
            $b = Booking::where('msisdn', $session->phone)->orderByDesc('id')->first();
        }

        if (! $b) {
            Log::warning('Flow finale: booking not found', ['id' => $id, 'code' => $code]);

            return;
        }

        $this->sendBookingConfirmation($b, $session);

        // Optional: append DB-defined finale trigger (if active)
        if ($finale = $this->findTrigger('finale')) {
            $this->sendTrigger($finale, $session);
        }
    }

    protected function sendBookingConfirmation(Booking $b, WhatsappSession $session): void
    {
        $keyBase = sprintf('wa:confirm:%d:%s', $b->id, $b->booking_code);
        if (! cache()->add($keyBase, 1, now()->addMinutes(10))) {
            \Log::info('WA confirm skipped (duplicate)', ['booking_id' => $b->id, 'code' => $b->booking_code]);

            return;
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $locale = $session->locale === 'ar' ? 'ar' : 'en';

        $dt = $this->buildLocalDateTime($b->res_date, $b->res_time, $tz);
        if ($dt) {
            $dt = $dt->locale($locale);
            $dateText = $dt->isoFormat($locale === 'ar' ? 'ddd D MMM' : 'ddd, MMM D');
            $timeText = $dt->isoFormat($locale === 'ar' ? 'h:mm a' : 'h:mm A');
        } else {
            // fallback so the webhook never crashes
            $dateText = (string) $b->res_date;
            $timeText = (string) $b->res_time;
        }

        // 1) QR first
        $qrSvc = $this->qr;
        $qrUrl = route('bookings.qr', ['token' => $qrSvc->ensureToken($b)->qr_token]);
        $caption = $this->messages->get('finale.confirm', $locale, [
            'code' => $b->booking_code,
            'datetime' => "{$dateText} • {$timeText}",
            'party' => (string) $b->party_size,
            'pass_url' => $qrSvc->passUrl($b),
        ]);

        \Log::info('WA confirm: sending QR', ['booking_id' => $b->id, 'code' => $b->booking_code, 'to' => $session->phone]);
        if (! $this->sender()->sendImage($session->phone, $qrUrl, $caption)) {
            $this->sender()->sendTextMessage($session->phone, $caption);
        }

        // 2) Confirmation template (separate idempotency)
        $tplKey = "{$keyBase}:tpl";
        if (cache()->add($tplKey, 1, now()->addMinutes(10))) {
            \Log::info('WA confirm: sending template', ['booking_id' => $b->id, 'code' => $b->booking_code, 'to' => $session->phone]);
            $ok = $this->tpl->bookingConfirmed($session, $b);
            \Log::info('WA confirm: template result', ['ok' => $ok, 'booking_id' => $b->id, 'code' => $b->booking_code]);
        }
    }

    /* ---------------- TRIGGER HELPERS ---------------- */

    protected function findTrigger(string $type, ?string $keyword = null): ?WhatsappTrigger
    {
        $q = WhatsappTrigger::query()
            ->where('type', $type)
            ->where('is_active', true);

        if ($type === 'keyword' && $keyword !== null) {
            $q->where('keyword', $keyword);
        }

        return $q->first();
    }

    protected function maybeSendKeywordTrigger(string $needle, WhatsappSession $session): bool
    {
        // Exact match on normalized keyword (DB stores exact keyword)
        if ($t = $this->findTrigger('keyword', $needle)) {
            $this->sendTrigger($t, $session);

            return true;
        }

        return false;
    }

    protected function sendTrigger(WhatsappTrigger $trigger, WhatsappSession $session): void
    {
        $locale = $session->locale === 'ar' ? 'ar' : 'en';
        $message = $trigger->getResponseMessage($locale);

        // For now, only 'text' is implemented in schema; future-proof by checking type.
        $type = $trigger->response_type ?: 'text';

        switch ($type) {
            case 'text':
            default:
                $this->sender()->sendTextMessage($session->phone, (string) $message);
                break;
        }
    }

    protected function normalizeKeyword(string $text): string
    {
        // Keep Arabic & Latin words; trim punctuation around
        $t = trim(mb_strtolower($text, 'UTF-8'));
        // Strip surrounding emojis/punctuation/spaces
        $t = preg_replace('/^[\s[:punct:]\x{2000}-\x{206F}\x{1F300}-\x{1FAFF}]+|[\s[:punct:]\x{2000}-\x{206F}\x{1F300}-\x{1FAFF}]+$/u', '', $t);

        return $t ?: $text;
    }

    /* ---------------- misc helpers ---------------- */

    protected function sender(): WhatsAppSender
    {
        return new WhatsAppSender($this->waFactory->make());
    }

    protected function inferLocale(?string $text): ?string
    {
        if (! $text) {
            return null;
        }
        if (preg_match('/\p{Arabic}/u', $text)) {
            return 'ar';
        }
        if (preg_match('/[A-Za-z]{2,}/', $text)) {
            return 'en';
        }

        return null;
    }

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
        $date = Str::of($date)->before(' ')->value();       // strip any time from date

        // normalize time; if it contains date, strip it
        $timeOnly = Str::of($time)->after(' ')->value();    // strip any date from time
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

    private function maybeHandleWorkingHoursQuickReply(array $payload, WhatsappSession $session): bool
    {
        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        if (! $message) {
            return false;
        }

        $title = null;
        $type = $message['type'] ?? null;

        if ($type === 'button') {
            $title = $message['button']['text'] ?? null;
        } elseif ($type === 'interactive') {
            // button_reply or list_reply
            $title = $message['interactive']['button_reply']['title']
                  ?? $message['interactive']['list_reply']['title']
                  ?? null;
        } elseif ($type === 'text') {
            $title = $message['text']['body'] ?? null;
        }

        if (! is_string($title) || $title === '') {
            return false;
        }

        $normalized = mb_strtolower(trim($title));
        $isWorkingHours = in_array($normalized, [
            'working hours', 'work hours', 'opening hours', 'opening time', 'hours', 'timings',
            'ساعات العمل', 'اوقات العمل', 'مواعيد العمل', 'الدوام',
        ], true);

        if (! $isWorkingHours) {
            return false;
        }

        $this->sendBranchWorkingHours($session);

        return true;
    }

    private function sendBranchWorkingHours(WhatsappSession $session): void
    {
        $branchId = $this->ui->defaultBranchId(); // Use UI service helper
        $locale = $session->locale === 'ar' ? 'ar' : 'en';
        $tz = config('app.timezone', 'Asia/Kuwait');

        $branch = \App\Models\Branch::find($branchId);
        if (! $branch) {
            $this->sender()->sendTextMessage(
                $session->phone,
                $locale === 'ar'
                    ? 'عذراً، لم نتمكن من تحديد الفرع. الرجاء المحاولة لاحقاً.'
                    : 'Sorry, we could not determine the branch. Please try again later.'
            );

            return;
        }

        // Title
        $val = $branch->name ?? null;
        $branchName = '';
        if (is_array($val)) {
            $branchName = $val[$locale] ?? ($val['en'] ?? '');
        } elseif (is_string($val)) {
            $branchName = $val;
        }

        $titleSuffix = $branchName !== '' ? " — {$branchName}" : '';

        $title = $locale === 'ar'
            ? "🕒 *ساعات العمل{$titleSuffix}*"
            : "🕒 *Working Hours{$titleSuffix}*";

        // Build pretty lines
        $lines = $this->formatWeeklyHoursPretty($branchId, $locale, $tz);

        $msg = $title."\n\n".implode("\n", $lines);
        $this->sender()->sendTextMessage($session->phone, $msg);
    }

    private function formatWeeklyHoursPretty(int $branchId, string $locale, string $tz): array
    {
        $rules = \App\Models\BranchAvailabilityRule::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy('day_of_week');

        // 0..6 (Sun..Sat)
        $labelsEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $labelsAr = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        $labels = $locale === 'ar' ? $labelsAr : $labelsEn;

        $now = \Carbon\Carbon::now($tz);
        $today = (int) $now->dayOfWeek;

        $fmtTime = function (\Carbon\Carbon $t) use ($locale) {
            $s = $t->format('h:i a');               // 12h → e.g. 02:00 pm
            if ($locale === 'ar') {
                $s = strtr($s, ['am' => 'ص', 'pm' => 'م']); // Arabic markers
            }

            return $s;
        };

        $line = function (string $label, string $body, bool $isToday) use ($locale) {
            if ($isToday) {
                return $locale === 'ar'
                    ? "• *اليوم ({$label})* — {$body}"
                    : "• *Today ({$label})* — {$body}";
            }

            return "• {$label} — {$body}";
        };

        $lines = [];
        for ($d = 0; $d <= 6; $d++) {
            $r = $rules->get($d);
            $label = $labels[$d];

            if (! $r || ! (int) $r->is_open) {
                $body = $locale === 'ar' ? 'مغلق' : 'Closed';
                $lines[] = $line($label, $body, $d === $today);

                continue;
            }

            $open = \Carbon\Carbon::createFromFormat('H:i:s', $r->open_at, $tz)->setDate($now->year, $now->month, $now->day);
            $close = \Carbon\Carbon::createFromFormat('H:i:s', $r->close_at, $tz)->setDate($now->year, $now->month, $now->day);

            // Spans past midnight
            if ($close->lessThanOrEqualTo($open)) {
                $close->addDay();
            }

            // Optional: “24 hours” shortcut
            $is24h = $r->open_at === '00:00:00' && $r->close_at === '23:59:00';
            if ($is24h) {
                $body = $locale === 'ar' ? 'طوال اليوم' : '24 hours';
            } else {
                $body = $fmtTime($open).' – '.$fmtTime($close); // en-dash
            }

            $lines[] = $line($label, $body, $d === $today);
        }

        return $lines;
    }
}
