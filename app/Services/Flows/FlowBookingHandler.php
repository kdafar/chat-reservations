<?php

namespace App\Services\Flows;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\WhatsappSession;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\HoldService;
use App\Services\MessageCatalog;
use App\Services\QrPassService;
use App\Services\WAFlow\FlowAssets;
use App\Services\WAFlow\FlowCtx;
use App\Services\WAFlow\FlowStateStore;
use App\Services\WhatsAppApiServiceFactory;
use App\Services\WhatsAppSender;
use App\Services\WhatsAppTemplateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlowBookingHandler
{
    public function __construct(
        private FlowAssets $assets,
        private AvailabilityService $availability,
        private HoldService $holds,
        private BookingService $bookings,
        private FlowCtx $ctx,
        private FlowStateStore $store,
        private MessageCatalog $messages,
        // Added for Immediate Confirmation
        private QrPassService $qr,
        private WhatsAppTemplateService $tpl,
        private WhatsAppApiServiceFactory $waFactory,
    ) {}

    /** First node in your JSON graph. */
    private string $initialScreen = 'HOME';

    /**
     * Main Entry Point
     */
    public function handle(object $req, ?WhatsappSession $session, string $locale): array
    {
        $action = strtoupper((string) ($req->action ?? ''));
        $screen = strtoupper((string) ($req->screen ?? ''));
        $token = (string) ($req->flowToken ?? '');
        $data = (array) ($req->data ?? []);

        // 1. Ensure State Exists
        $state = $this->store->getOrCreate($token, $session?->phone, $this->initialScreen);

        // 2. Load Context (DB wins, session fallback)
        $c = $this->ctx->get($session, $state);

        // 3. Handle Client Errors (Bounce to Home)
        if (isset($data['error']) || isset($data['error_message'])) {
            Log::warning('Flow client error', [
                'err' => $data['error'] ?? 'unknown',
                'msg' => $data['error_message'] ?? '',
                'screen' => $screen,
            ]);
            $this->ctx->setScreen($token, $this->initialScreen);

            return $this->respond($this->initialScreen, $this->screenHome($session, $c, $locale));
        }

        // 4. Handle Init / Cold Start
        if ($action === 'INIT' || ($action === 'DATA_EXCHANGE' && $screen === '')) {
            $this->ctx->setScreen($token, $this->initialScreen);

            return $this->respond($this->initialScreen, $this->screenHome($session, $c, $locale));
        }

        // 5. Normalize Action Triggers
        $act = $this->canonAction($data);

        // 6. Router
        switch ($screen) {
            /* -----------------------------------------------------------------
               FLOW: HOME
               ----------------------------------------------------------------- */
            case 'HOME':
                $choice = (string) ($data['home_choice'] ?? '');

                if ($choice === 'new_booking') {
                    // CRITICAL: Clear all downstream state for a fresh start
                    $this->ctx->clear($token, ['clinic_id', 'branch_id', 'doctor_id', 'res_date', 'res_time', 'party_size']);
                    $this->ctx->put($token, ['__state' => 'FLOW'], $session);

                    $this->ctx->setScreen($token, 'SELECT_BRANCH');

                    return $this->respond('SELECT_BRANCH', $this->screenSelectBranch($session, $this->ctx->all($token), $locale));
                }

                if ($choice === 'view_bookings') {
                    return $this->tryTransitionToBookings($token, $session, $locale);
                }

                return $this->respond('HOME', $this->screenHome($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   FLOW: SELECT BRANCH (New Multi-Step)
                   ----------------------------------------------------------------- */
            case 'SELECT_BRANCH':
                $clinicId = $data['clinic_id'] ?? $c['clinic_id'] ?? null;

                if ($act === 'clinic_selected') {
                    // STATE HYGIENE: Clinic changed? Clear Branch & Doctor.
                    $this->ctx->put($token, [
                        'clinic_id' => $clinicId,
                        'branch_id' => null,
                        'doctor_id' => null,
                    ], $session);

                    // Force refresh context for immediate UI update
                    $refreshCtx = $this->ctx->all($token);
                    $refreshCtx['clinic_id'] = $clinicId;

                    return $this->respond('SELECT_BRANCH', $this->screenSelectBranch($session, $refreshCtx, $locale));
                }

                if ($act === 'branch_selected') {
                    $branchId = (int) ($data['branch_id'] ?? 0);
                    if ($branchId > 0) {
                        $this->ctx->put($token, [
                            'clinic_id' => $clinicId,
                            'branch_id' => $branchId,
                            'doctor_id' => null, // Clear doctor logic
                        ], $session);

                        $this->ctx->setScreen($token, 'SELECT_DOCTOR');

                        return $this->respond('SELECT_DOCTOR', $this->screenSelectDoctor($session, $this->ctx->all($token), $locale));
                    }
                }

                return $this->respond('SELECT_BRANCH', $this->screenSelectBranch($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   FLOW: SELECT DOCTOR (New Step)
                   ----------------------------------------------------------------- */
            case 'SELECT_DOCTOR':
                if ($act === 'doctor_selected') {
                    $docRaw = $data['doctor_id'] ?? null;

                    // Handle "any" string vs specific ID
                    $doctorId = ($docRaw === 'any') ? null : (int) $docRaw;

                    $this->ctx->put($token, ['doctor_id' => $doctorId], $session);
                    $this->ctx->setScreen($token, 'APPOINTMENT');

                    return $this->respond('APPOINTMENT', $this->screenAppointment($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('SELECT_DOCTOR', $this->screenSelectDoctor($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   FLOW: APPOINTMENT (Date/Time/Type)
                   ----------------------------------------------------------------- */
            case 'APPOINTMENT':
                // Update context on triggers
                if ($act === 'party_size_selected' && isset($data['party_size'])) {
                    $this->ctx->put($token, ['party_size' => (string) $data['party_size']], $session);
                }

                if ($act === 'date_selected' && ! empty($data['date'])) {
                    $this->ctx->put($token, ['res_date' => (string) $data['date']], $session);
                }

                if ($act === 'continue_to_details') {
                    $patch = [
                        'party_size' => isset($data['party_size']) ? (string) $data['party_size'] : ($c['party_size'] ?? 'general'),
                        'res_date' => (string) ($data['date'] ?? ''),
                        'res_time' => (string) ($data['time'] ?? ''),
                    ];
                    $this->ctx->put($token, $patch, $session);

                    $this->ctx->setScreen($token, 'DETAILS');

                    return $this->respond('DETAILS', $this->screenDetails($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('APPOINTMENT', $this->screenAppointment($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   FLOW: DETAILS
                   ----------------------------------------------------------------- */
            case 'DETAILS':
                if ($act === 'continue_to_summary') {
                    $this->ctx->put($token, [
                        'name' => (string) ($data['name'] ?? ''),
                        'phone' => (string) ($data['phone'] ?? ''),
                        'email' => (string) ($data['email'] ?? ''),
                        'notes' => (string) ($data['notes'] ?? ''),
                    ], $session);

                    $this->ctx->setScreen($token, 'SUMMARY');

                    return $this->respond('SUMMARY', $this->screenSummary($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('DETAILS', $this->screenDetails($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   FLOW: SUMMARY
                   ----------------------------------------------------------------- */
            case 'SUMMARY':
                if (isset($data['agree_terms'])) {
                    $this->ctx->put($token, ['agree_terms' => filter_var($data['agree_terms'], FILTER_VALIDATE_BOOL)], $session);
                }

                if ($act === 'confirm_booking') {
                    return $this->onSummaryConfirm($token, $session, $locale);
                }

                return $this->respond('SUMMARY', $this->screenSummary($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   LEGACY: HOME RETURN (Post-Flow)
                   ----------------------------------------------------------------- */
            case 'HOME_RETURN':
                $choice = (string) ($data['home_choice'] ?? '');
                $this->ctx->put($token, ['__state' => 'IDLE'], $session);

                if ($choice === 'new_booking') {
                    $this->ctx->setScreen($token, 'SELECT_BRANCH');

                    return $this->respond('SELECT_BRANCH', $this->screenSelectBranch($session, $this->ctx->all($token), $locale));
                }

                if ($choice === 'view_bookings') {
                    return $this->tryTransitionToBookings($token, $session, $locale);
                }

                if ($choice === 'end_session') {
                    $this->ctx->put($token, ['session_end' => true], $session);
                    $this->ctx->setScreen($token, 'CONFIRMATION');

                    return $this->respond('CONFIRMATION', $this->screenConfirmation($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('HOME_RETURN', $this->screenHome($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   LEGACY: BOOKINGS LIST
                   ----------------------------------------------------------------- */
            case 'BOOKINGS':
                $bid = (int) ($data['booking_id'] ?? 0);

                if ($bid > 0) {
                    $this->ctx->put($token, [
                        'active_booking_id' => $bid,
                        'edit_booking_id' => $bid,
                    ], $session);

                    $this->ctx->setScreen($token, 'MANAGE_BOOKING');

                    return $this->respond('MANAGE_BOOKING', $this->screenManageBooking($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('BOOKINGS', $this->screenBookings($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   LEGACY: MANAGE BOOKING
                   ----------------------------------------------------------------- */
            case 'MANAGE_BOOKING':
                $bid = (int) ($c['edit_booking_id'] ?? $c['active_booking_id'] ?? 0);

                if ($act === 'edit_datetime') {
                    if ($bid && ($b = Booking::find($bid))) {
                        $this->ctx->put($token, [
                            'branch_id' => $b->branch_id,
                            'party_size' => $b->party_size, // Legacy int or string
                            'res_date' => $b->res_date,
                            'res_time' => $b->res_time,
                            'edit_booking_id' => $b->id,
                        ], $session);
                    }
                    $this->ctx->setScreen($token, 'EDIT_APPOINTMENT');

                    return $this->respond('EDIT_APPOINTMENT', $this->screenEditAppointment($session, $this->ctx->all($token), $locale));
                }

                if ($act === 'edit_party') {
                    if ($bid && ($b = Booking::find($bid))) {
                        $this->ctx->put($token, [
                            'branch_id' => $b->branch_id,
                            'party_size' => $b->party_size,
                            'res_date' => $b->res_date,
                            'res_time' => $b->res_time,
                            'edit_booking_id' => $b->id,
                        ], $session);
                    }
                    $this->ctx->setScreen($token, 'EDIT_PARTY');

                    return $this->respond('EDIT_PARTY', $this->screenEditParty($session, $this->ctx->all($token), $locale));
                }

                if ($act === 'cancel') {
                    $this->ctx->setScreen($token, 'CANCEL_CONFIRM');

                    return $this->respond('CANCEL_CONFIRM', $this->screenCancelConfirm($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('MANAGE_BOOKING', $this->screenManageBooking($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   LEGACY: EDIT APPOINTMENT
                   ----------------------------------------------------------------- */
            case 'EDIT_APPOINTMENT':
                $bid = (int) ($c['edit_booking_id'] ?? $c['active_booking_id'] ?? ($data['booking_id'] ?? 0));

                // Load existing booking for safe defaults (doctor/branch/party)
                $existingBooking = null;
                if ($bid > 0) {
                    $existingBooking = \App\Models\Booking::query()->find($bid);
                }

                if ($act === 'date_changed') {
                    $date = (string) ($data['date'] ?? '');
                    if ($date !== '') {
                        $date = preg_replace('/\s.*/', '', trim($date)) ?: $date;
                        $this->ctx->put($token, ['res_date' => $date], $session);
                    }

                    return $this->respond(
                        'EDIT_APPOINTMENT',
                        $this->screenEditAppointment($session, $this->ctx->all($token), $locale)
                    );
                }

                if ($act === 'save_datetime') {
                    $rawDate = (string) ($data['date'] ?? ($c['res_date'] ?? ($existingBooking?->res_date ?? '')));
                    $rawTime = (string) ($data['time'] ?? ($c['res_time'] ?? ($existingBooking?->res_time ?? '')));

                    // IMPORTANT: keep party size consistent with the booking (default 1 if missing)
                    $party = (int) ($c['party_size'] ?? ($existingBooking?->party_size ?? 1));
                    $party = max(1, $party);

                    // Keep branch/doctor stable: user is editing datetime, not switching doctor/branch
                    $branchId = (int) ($c['branch_id'] ?? ($existingBooking?->branch_id ?? $this->defaultBranchId()));
                    $doctorId = (int) ($c['doctor_id'] ?? ($existingBooking?->doctor_id ?? 0));
                    $doctorId = $doctorId > 0 ? $doctorId : null;

                    $msisdn = (string) ($session?->phone ?? '');
                    $msisdn = trim($msisdn);
                    $msisdnDigits = $msisdn !== '' ? preg_replace('/\D+/', '', $msisdn) : '';
                    $msisdn = $msisdnDigits ?: $msisdn;

                    // Normalize date/time
                    $date = preg_replace('/\s.*/', '', trim($rawDate)) ?: $rawDate;
                    $time = trim($rawTime);
                    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                        $time .= ':00';
                    }

                    if ($bid && $branchId && $date && $time && $msisdn) {
                        // Hold remains "lightweight" (signature unchanged)
                        $this->holds->hold($branchId, $date, $time, $party, $msisdn, minutes: 5);

                        $holdRow = $this->holds->findActive("{$date}@{$time}@{$party}@{$branchId}");

                        try {
                            $booking = $this->bookings->confirmFromHold(
                                $holdRow ?: [
                                    'branch_id' => $branchId,
                                    'res_date' => $date,
                                    'res_time' => $time,
                                    'party_size' => $party,
                                    'msisdn' => $msisdn,
                                    'source' => 'whatsapp',
                                ],
                                [
                                    'existing_booking_id' => $bid,
                                    'branch_id' => $branchId,

                                    // Ensure doctor stays attached (and conflict guard applies)
                                    'doctor_id' => $doctorId,

                                    'res_date' => $date,
                                    'res_time' => $time,
                                    'party_size' => $party,

                                    'source' => 'whatsapp',
                                    // optional, if you have something meaningful:
                                    // 'source_ref' => (string) ($token ?? null),

                                    'locale' => $locale,
                                    'msisdn' => $msisdn,
                                    'name' => $c['name'] ?? null,
                                    'agree_terms' => (bool) ($c['agree_terms'] ?? false),
                                ]
                            );

                        } catch (\RuntimeException $e) {
                            // PATCH: doctor slot conflict (raised by BookingService)
                            if ($e->getMessage() === 'SLOT_TAKEN') {
                                $this->ctx->put($token, [
                                    'error' => 'SLOT_TAKEN', // let screen show proper localized message
                                    'res_date' => $date,
                                    'res_time' => $time,
                                ], $session);

                                return $this->respond(
                                    'EDIT_APPOINTMENT',
                                    $this->screenEditAppointment($session, $this->ctx->all($token), $locale)
                                );
                            }

                            throw $e;
                        } finally {
                            // Always release holds
                            $this->holds->releaseByPhoneAndBranch($msisdn, $branchId);
                        }

                        // QR is dispatched on the user's "Done" tap, not here.

                        $this->ctx->put($token, [
                            'booking_id' => $booking->id,
                            'booking_code' => $booking->booking_code,
                            'res_date' => (string) $booking->res_date,
                            'res_time' => (string) $booking->res_time,
                        ], $session);

                        $this->ctx->setScreen($token, 'EDIT_SUCCESS');

                        return $this->respond(
                            'EDIT_SUCCESS',
                            $this->screenEditSuccess($session, $this->ctx->all($token), $locale)
                        );
                    }

                    return $this->respond(
                        'EDIT_APPOINTMENT',
                        $this->screenEditAppointment($session, $this->ctx->all($token), $locale)
                    );
                }

                return $this->respond(
                    'EDIT_APPOINTMENT',
                    $this->screenEditAppointment($session, $this->ctx->all($token), $locale)
                );

                /* -----------------------------------------------------------------
                   LEGACY: EDIT PARTY
                   ----------------------------------------------------------------- */
            case 'EDIT_PARTY':
                // Keeping minimal implementation for legacy support
                return $this->respond('EDIT_PARTY', $this->screenEditParty($session, $this->ctx->all($token), $locale));

                /* -----------------------------------------------------------------
                   LEGACY: SUCCESS & CANCEL
                   ----------------------------------------------------------------- */
            case 'EDIT_SUCCESS':
                $act = strtolower(trim((string) ($data['trigger'] ?? $data['next_action'] ?? '')));
                if ($act === 'go_home') {
                    $this->ctx->setScreen($token, 'HOME_RETURN');

                    return $this->respond('HOME_RETURN', $this->screenHome($session, $this->ctx->all($token), $locale));
                }
                if ($act === 'view_bookings') {
                    return $this->tryTransitionToBookings($token, $session, $locale);
                }
                if ($act === 'end_session') {
                    $this->ctx->put($token, ['session_end' => true], $session);
                    $this->ctx->setScreen($token, 'CONFIRMATION');

                    return $this->respond('CONFIRMATION', $this->screenConfirmation($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('EDIT_SUCCESS', $this->screenEditSuccess($session, $this->ctx->all($token), $locale));

            case 'CANCEL_CONFIRM':
                if ($act === 'cancel_booking' && ($data['confirmed'] ?? false)) {
                    $bid = (int) ($data['booking_id'] ?? ($c['edit_booking_id'] ?? $c['active_booking_id'] ?? 0));
                    if ($bid > 0 && ($b = Booking::find($bid))) {
                        $b->update([
                            'status' => 'cancelled',
                            'cancel_reason' => (string) ($data['reason'] ?? ''),
                            'cancel_notes' => (string) ($data['comments'] ?? ''),
                        ]);
                        $this->ctx->put($token, [
                            'cancelled_date' => $b->res_date,
                            'cancelled_time' => $b->res_time,
                            'cancelled_booking_id' => $b->id,
                        ], $session);
                    }
                    $this->ctx->clear($token, ['edit_booking_id', 'active_booking_id']);
                    $this->ctx->setScreen($token, 'CANCEL_SUCCESS');

                    return $this->respond('CANCEL_SUCCESS', $this->screenCancelSuccess($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('CANCEL_CONFIRM', $this->screenCancelConfirm($session, $this->ctx->all($token), $locale));

            case 'CANCEL_SUCCESS':
                $act = strtolower(trim((string) ($data['trigger'] ?? $data['next_action'] ?? '')));
                if ($act === 'go_home') {
                    $this->ctx->setScreen($token, 'HOME_RETURN');

                    return $this->respond('HOME_RETURN', $this->screenHome($session, $this->ctx->all($token), $locale));
                }
                if ($act === 'view_bookings') {
                    return $this->tryTransitionToBookings($token, $session, $locale);
                }
                if ($act === 'end_session') {
                    $this->ctx->put($token, ['session_end' => true], $session);
                    $this->ctx->setScreen($token, 'CONFIRMATION');

                    return $this->respond('CONFIRMATION', $this->screenConfirmation($session, $this->ctx->all($token), $locale));
                }

                return $this->respond('CANCEL_SUCCESS', $this->screenCancelSuccess($session, $this->ctx->all($token), $locale));

            case 'CONFIRMATION':
                // User tapped "Done" / triggered end_session from the booking
                // confirmation screen: dispatch the QR now and flip the
                // screen into its terminal (session-ended) state.
                if ($act === 'end_session') {
                    $bid = (int) ($c['booking_id'] ?? 0);
                    if ($bid > 0 && $session) {
                        if ($booking = Booking::find($bid)) {
                            try {
                                $this->sendBookingConfirmation($booking, $session);
                            } catch (\Throwable $e) {
                                \Log::error('WA confirm: send QR on Done failed', [
                                    'booking_id' => $bid,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                    $this->ctx->put($token, ['session_end' => true], $session);
                }

                return $this->respond('CONFIRMATION', $this->screenConfirmation($session, $this->ctx->all($token), $locale));

            default:
                $this->ctx->setScreen($token, $this->initialScreen);

                return $this->respond($this->initialScreen, $this->screenHome($session, $this->ctx->all($token), $locale));
        }
    }

    /**
     * Safety transition: If no bookings exist, fall back to Home to avoid crash.
     */
    private function tryTransitionToBookings(string $token, ?WhatsappSession $session, string $locale): array
    {
        $payload = $this->screenBookings($session, $this->ctx->all($token), $locale);

        if (empty($payload['bookings'])) {
            $this->ctx->setScreen($token, 'HOME_RETURN');

            return $this->respond('HOME_RETURN', $this->screenHome($session, $this->ctx->all($token), $locale));
        }

        $this->ctx->setScreen($token, 'BOOKINGS');

        return $this->respond('BOOKINGS', $payload);
    }

    /* =========================== SCREENS (DATA GENERATORS) =========================== */

    private function screenHome(?WhatsappSession $session, array $c, string $locale): array
    {
        $msisdn = (string) ($session?->phone ?? '');
        $hasBookings = $msisdn !== '' && $this->upcomingBookingsQuery($msisdn)->exists();
        $rawName = trim((string) ($c['name'] ?? $session?->name ?? ''));

        return [
            'customer_name' => $this->welcomeHeading($rawName, $locale),
            'has_bookings' => (bool) $hasBookings,
            'welcome_message' => $this->welcomeMessage($locale),
        ];
    }

    private function screenSelectBranch(?WhatsappSession $session, array $c, string $locale): array
    {
        // Safe Translation Wrapper for Partner
        $clinics = Partner::where('is_active', true)->get()->map(fn ($p) => [
            'id' => (string) $p->id,
            'title' => $this->safeTrans($p, 'name', $locale),
        ])->values()->all();

        // SAFETY: WA Flow RadioButtons cannot have empty dataSource
        if (empty($clinics)) {
            $clinics[] = [
                'id' => 'no_clinic',
                'title' => $locale === 'ar' ? 'غير متوفر' : 'Not Available',
                'enabled' => false,
            ];
        }

        $selectedClinicId = $c['clinic_id'] ?? null;
        $branches = [];

        if ($selectedClinicId) {
            // Safe Translation Wrapper for Branch
            $branches = Branch::where('partner_id', $selectedClinicId)
                ->where('is_available', true)
                ->get()
                ->map(fn ($b) => [
                    'id' => (string) $b->id,
                    'title' => $this->safeTrans($b, 'name', $locale),
                    'description' => (string) ($b->address ?? ''),
                ])->values()->all();
        }

        // SAFETY: Ensure branches array is not empty if shown, OR rely on 'show_branches' logic
        // But some components crash if the referenced dataSource is empty regardless of visibility.
        // We add a dummy just in case.
        if (empty($branches)) {
            $branches[] = [
                'id' => 'no_branch',
                'title' => $locale === 'ar' ? 'لا توجد فروع' : 'No Branches',
                'description' => '',
                'enabled' => false,
            ];
        }

        return [
            'clinics' => $clinics,
            'branches' => $branches,
            'show_branches' => $selectedClinicId && count($branches) > 0 && ($branches[0]['id'] !== 'no_branch'),
        ];
    }

    private function screenSelectDoctor(?WhatsappSession $session, array $c, string $locale): array
    {
        $branchId = (int) ($c['branch_id'] ?? 0);

        $doctors = [];

        // Logic: Try to fetch doctors if branch is valid
        if ($branchId > 0) {
            $doctors = Doctor::where('branch_id', $branchId)
                ->where('is_active', true)
                ->get()
                ->map(fn ($d) => [
                    'id' => (string) $d->id,
                    'title' => $this->safeTrans($d, 'name', $locale),
                    'description' => $this->safeTrans($d, 'specialty', $locale),
                ])->values()->all();
        }

        // CRITICAL FIX: "RadioButtonsGroup 'doctor_id' must contain at least 1 options"
        // We MUST add the fallback "Any" option regardless of whether doctors exist or branchId is valid.
        $anyOption = [
            'id' => 'any',
            'title' => $locale === 'ar' ? 'أي دكتور' : 'Any Doctor',
            'description' => $locale === 'ar' ? 'موعد عام' : 'General Appointment',
        ];

        // Ensure array is valid before unshift
        if (! is_array($doctors)) {
            $doctors = [];
        }

        array_unshift($doctors, $anyOption);

        return [
            'doctors' => $doctors,
        ];
    }

    private function screenAppointment(?WhatsappSession $session, array $c, string $locale): array
    {
        $branchId = (int) ($c['branch_id'] ?? $this->defaultBranchId());

        // Note: party_size is now a STRING (type) not INT (count) in the JSON flow.
        // We assume 1 person for availability checks.
        $availabilityPartySize = 1;

        [$minDate, $maxDate, $unavailableDates] = $this->calendarFor($branchId, $availabilityPartySize);

        $datePrefill = (string) ($c['res_date'] ?? $this->firstAvailableDate($minDate, $maxDate, $unavailableDates));

        $timeItems = [];
        $hasTimes = false;

        if ($datePrefill !== '') {
            $doctorId = (int) ($c['doctor_id'] ?? 0);

            // Check availability with or without doctor constraint
            $times = $this->availability->timesFor($branchId, $datePrefill, $availabilityPartySize, $doctorId > 0 ? $doctorId : null);

            foreach ($times as $t) {
                $timeItems[] = [
                    'id' => (string) $t['value'],
                    'title' => (string) $t['label'],
                    'enabled' => true,
                ];
            }
            $hasTimes = count($timeItems) > 0;
        }

        // SAFETY: If no times, provide dummy to prevent crash if component is rendered
        if (empty($timeItems)) {
            $timeItems[] = [
                'id' => 'no_slots',
                'title' => $locale === 'ar' ? 'لا توجد أوقات' : 'No times available',
                'enabled' => false,
            ];
        }

        $partyOpts = $locale === 'ar'
            ? [
                ['id' => 'general', 'title' => 'كشف عام'],
                ['id' => 'dental', 'title' => 'أسنان'],
                ['id' => 'specialist', 'title' => 'استشارة مختص'],
            ]
            : [
                ['id' => 'general', 'title' => 'General Consultation'],
                ['id' => 'dental', 'title' => 'Dental Checkup'],
                ['id' => 'specialist', 'title' => 'Specialist Consultation'],
            ];

        return [
            'available_party_sizes' => $partyOpts,
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'unavailable_dates' => $unavailableDates,
            'available_times' => $timeItems,
            'show_times' => $hasTimes,
        ];
    }

    private function screenDetails(?WhatsappSession $session, array $c, string $locale): array
    {
        $prefills = $this->prefill($session, $c);

        return [
            'party_size' => (string) ($c['party_size'] ?? ''),
            'date' => (string) ($c['res_date'] ?? ''),
            'time' => (string) ($c['res_time'] ?? ''),
            'prefill_name' => $prefills['name'],
            'prefill_phone' => $prefills['phone'],
            'prefill_email' => $prefills['email'],
            'booking_summary_text' => $this->fmtDetailsSummary($c, $locale),
        ];
    }

    private function screenSummary(?WhatsappSession $session, array $c, string $locale): array
    {
        $branchId = (int) ($c['branch_id'] ?? $this->defaultBranchId());
        $terms = $this->loadTerms($branchId, $locale);

        return [
            'party_size' => (string) ($c['party_size'] ?? ''),
            'date' => (string) ($c['res_date'] ?? ''),
            'time' => (string) ($c['res_time'] ?? ''),
            'name' => (string) ($c['name'] ?? ''),
            'phone' => (string) ($c['phone'] ?? ''),
            'email' => (string) ($c['email'] ?? ''),
            'notes' => (string) ($c['notes'] ?? ''),
            'terms_text' => $terms['terms_text'],
            'reservation_details_text' => $this->fmtSummaryReservation($c, $locale),
            'contact_details_text' => $this->fmtSummaryContact($c, $locale),
            'terms_label' => $terms['terms_label'],
            'terms_required' => (bool) $terms['terms_required'],
        ];
    }

    private function screenConfirmation(?WhatsappSession $session, array $c, string $locale): array
    {
        $sessionEnd = (bool) ($c['session_end'] ?? false);

        if ($sessionEnd) {
            $heading = $locale === 'ar' ? 'تم إنهاء الجلسة' : 'Session ended';
            $sub = $locale === 'ar' ? 'يمكنك فتح تدفّق الحجز مرة أخرى في أي وقت.' : 'You can reopen the booking flow anytime from this chat.';

            return [
                'booking_id' => (string) ($c['booking_id'] ?? ''),
                'booking_code' => (string) ($c['booking_code'] ?? ''),
                'confirmation_heading' => $heading,
                'confirmation_subheading' => $sub,
                'show_details' => false,
                'confirmation_details_text' => '',
                'end_session' => true,
            ];
        }

        $heading = $locale === 'ar' ? '🎉 تم تأكيد الحجز!' : '🎉 Booking Confirmed!';
        $sub = $locale === 'ar' ? 'شكراً لك! تم تأكيد حجزك.' : 'Thank you! Your reservation is confirmed.';

        return [
            'booking_id' => (string) ($c['booking_id'] ?? ''),
            'booking_code' => (string) ($c['booking_code'] ?? ''),
            'date' => (string) ($c['res_date'] ?? ''),
            'time' => (string) ($c['res_time'] ?? ''),
            'party_size' => (string) ($c['party_size'] ?? ''),
            'confirmation_heading' => $heading,
            'confirmation_subheading' => $sub,
            'show_details' => true,
            'confirmation_details_text' => $this->fmtConfirmationDetails($c, $locale),
            'end_session' => false,
        ];
    }

    // -- Legacy Screens --

    private function screenBookings(?WhatsappSession $session, array $c, string $locale): array
    {
        $msisdn = (string) ($session?->phone ?? '');
        $rows = [];

        if ($msisdn !== '') {
            $upcoming = $this->upcomingBookingsQuery($msisdn)->limit(10)->get();
            foreach ($upcoming as $b) {
                $rows[] = [
                    'id' => (string) $b->id,
                    'title' => $this->fmtDateTime($b->res_date, $b->res_time, $locale),
                    'description' => ($locale === 'ar' ? 'مجموعة من ' : 'Party of ').$b->party_size,
                ];
            }
        }

        return ['bookings' => $rows];
    }

    private function screenManageBooking(?WhatsappSession $session, array $c, string $locale): array
    {
        $bid = (int) ($c['edit_booking_id'] ?? $c['active_booking_id'] ?? 0);
        $b = $bid > 0 ? Booking::with('contact')->find($bid) : null;

        $canModify = $b && $b->status === Booking::S_CONFIRMED;
        $canCancel = $b && in_array($b->status, [Booking::S_CONFIRMED, Booking::S_HOLD], true);

        $customerName = $b?->name
            ?? ($c['name'] ?? null)
            ?? ($session?->profile_name ?? null)
            ?? substr($b?->msisdn ?? $session?->phone ?? '', -4);

        return [
            'booking_id' => (string) $bid,
            'customer_name' => (string) $customerName,
            'date' => (string) ($b?->res_date ?? ''),
            'time' => (string) ($b?->res_time ?? ''),
            'party_size' => (string) ($b?->party_size ?? ''),
            'name' => (string) $customerName,
            'phone' => (string) ($b?->msisdn ?? ''),
            'can_modify' => (bool) $canModify,
            'can_cancel' => (bool) $canCancel,
            'booking_title' => $this->fmtManageBookingTitle($b, $locale),
            'booking_details_text' => $this->fmtManageBookingDetails($b, $locale),
        ];
    }

    private function screenEditAppointment(?WhatsappSession $session, array $c, string $locale): array
    {
        $branchId = (int) ($c['branch_id'] ?? $this->defaultBranchId());
        // For edits, we assume simple party logic
        $party = 1;

        [$minDate, $maxDate, $unavailableDates] = $this->calendarFor($branchId, $party);

        $date = (string) ($c['res_date'] ?? '');
        $timeItems = [];
        if ($date !== '') {
            $times = $this->availability->timesFor($branchId, $date, $party);
            foreach ($times as $t) {
                $timeItems[] = [
                    'id' => (string) $t['value'],
                    'title' => (string) $t['label'],
                    'enabled' => true,
                ];
            }
        }

        // SAFETY: Legacy flows might crash too if empty
        if (empty($timeItems)) {
            $timeItems[] = [
                'id' => 'no_slots',
                'title' => $locale === 'ar' ? 'لا توجد أوقات' : 'No times available',
                'enabled' => false,
            ];
        }

        $b = null;
        if ($id = (int) ($c['edit_booking_id'] ?? 0)) {
            $b = Booking::find($id);
        }

        return [
            'booking_id' => (string) ($b?->id ?? ''),
            'current_date' => (string) ($b?->res_date ?? $c['res_date'] ?? ''),
            'current_time' => (string) ($b?->res_time ?? $c['res_time'] ?? ''),
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'unavailable_dates' => $unavailableDates,
            'available_times' => $timeItems,
            'current_booking_text' => $this->fmtEditAppointmentCurrent($b, $c, $locale),
        ];
    }

    private function screenEditParty(?WhatsappSession $session, array $c, string $locale): array
    {
        $current = (string) ($c['party_size'] ?? 'general');
        $opts = $locale === 'ar'
            ? [
                ['id' => 'general', 'title' => 'كشف عام'],
                ['id' => 'specialist', 'title' => 'استشارة مختص'],
            ]
            : [
                ['id' => 'general', 'title' => 'General Consultation'],
                ['id' => 'specialist', 'title' => 'Specialist Consultation'],
            ];

        return [
            'booking_id' => (string) ($c['edit_booking_id'] ?? $c['active_booking_id'] ?? ''),
            'current_party_size' => $current,
            'available_party_sizes' => $opts,
            'current_party_text' => 'Current: '.$current,
        ];
    }

    private function screenEditSuccess(?WhatsappSession $session, array $c, string $locale): array
    {
        $bid = (int) ($c['edit_booking_id'] ?? $c['active_booking_id'] ?? 0);
        $b = $bid > 0 ? Booking::find($bid) : null;

        return [
            'booking_id' => (string) ($b?->id ?? ''),
            'date' => (string) ($b?->res_date ?? $c['res_date'] ?? ''),
            'time' => (string) ($b?->res_time ?? $c['res_time'] ?? ''),
            'party_size' => (string) ($b?->party_size ?? $c['party_size'] ?? ''),
            'success_message_text' => $this->fmtEditSuccessMessage($b, $c, $locale),
        ];
    }

    private function screenCancelConfirm(?WhatsappSession $session, array $c, string $locale): array
    {
        $bid = (int) ($c['edit_booking_id'] ?? $c['active_booking_id'] ?? 0);
        $b = $bid > 0 ? Booking::find($bid) : null;

        $reasons = config('reservations.cancel_reasons', [
            ['id' => 'change_plans',  'title' => $locale === 'ar' ? 'تغيير في الخطط' : 'Change of plans'],
            ['id' => 'emergency',     'title' => $locale === 'ar' ? 'حالة طارئة' : 'Emergency'],
        ]);

        return [
            'booking_id' => (string) $bid,
            'date' => (string) ($b?->res_date ?? ''),
            'time' => (string) ($b?->res_time ?? ''),
            'party_size' => (string) ($b?->party_size ?? ''),
            'cancellation_reasons' => $reasons,
            'cancel_details_text' => $this->fmtCancelDetails($b, $locale),
        ];
    }

    private function screenCancelSuccess(?WhatsappSession $session, array $c, string $locale): array
    {
        $bid = (string) ($c['cancelled_booking_id'] ?? '');
        $date = (string) ($c['cancelled_date'] ?? '');
        $time = (string) ($c['cancelled_time'] ?? '');

        $msg = $locale === 'ar'
            ? "تم إلغاء حجزك رقم #{$bid}."
            : "Your booking #{$bid} has been cancelled.";

        return [
            'booking_id' => $bid,
            'date' => $date,
            'time' => $time,
            'cancel_success_message' => $msg,
        ];
    }

    /* =========================== HELPERS & FORMATTERS =========================== */

    /**
     * Defensive translation getter.
     * Safely handles models that may or may not use HasTranslations trait.
     */
    private function safeTrans($model, string $field, string $locale): string
    {
        try {
            // 1. Try standard Spatie/Laravel-Translatable method
            if (method_exists($model, 'getTranslation')) {
                return (string) $model->getTranslation($field, $locale);
            }

            // 2. Access attribute directly (handled by accessor or $casts)
            $value = $model->$field;

            // 3. Check if it's an array (cast JSON)
            if (is_array($value)) {
                return (string) ($value[$locale] ?? $value['en'] ?? reset($value) ?? '');
            }

            // 4. If it's a raw JSON string
            if (is_string($value) && str_starts_with(trim($value), '{')) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return (string) ($decoded[$locale] ?? $decoded['en'] ?? '');
                }
            }

            return (string) $value;
        } catch (\Throwable $e) {
            \Log::warning("Translation failed for {$field}", ['error' => $e->getMessage()]);

            return '';
        }
    }

    private function onSummaryConfirm(string $token, ?WhatsappSession $session, string $locale): array
    {
        $c = $this->ctx->all($token);

        $branchId = (int) ($c['branch_id'] ?? 0);
        $doctorId = (int) ($c['doctor_id'] ?? 0);

        // Keep legacy ctx key (party_size sometimes is "general" in your flow)
        $partyType = (string) ($c['party_size'] ?? 'general');

        // NEW: numeric party size (default 1, clamp >=1)
        $partySize = (int) ($c['party_size_int'] ?? $c['party_size_number'] ?? 1);
        $partySize = max(1, $partySize);

        $date = (string) ($c['res_date'] ?? '');
        $time = (string) ($c['res_time'] ?? '');

        $msisdn = (string) ($c['phone'] ?? $session?->phone ?? '');
        $msisdn = trim($msisdn);
        $msisdnDigits = $msisdn !== '' ? preg_replace('/\D+/', '', $msisdn) : '';
        $msisdn = $msisdnDigits ?: $msisdn;

        $name = (string) ($c['name'] ?? '');
        $email = (string) ($c['email'] ?? '');
        $notes = (string) ($c['notes'] ?? '');
        $agree = (bool) ($c['agree_terms'] ?? false);

        if (! $branchId || $date === '' || $time === '') {
            return $this->respond('DETAILS', $this->screenDetails($session, $c + ['error' => true], $locale));
        }

        // Normalize date/time (match BookingService rules)
        $date = preg_replace('/\s.*/', '', trim($date)) ?: $date;
        $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        // Slot key MUST include party size (don’t hardcode 1)
        $slotKey = "{$date}@{$time}@{$partySize}@{$branchId}";

        // Ensure there is an active hold (keeps old system safe)
        // NOTE: we do not change hold signature; we just pass correct party size.
        $this->holds->hold($branchId, $date, $time, $partySize, $msisdn, minutes: 5);

        $holdRow = $this->holds->findActive($slotKey) ?? [
            'branch_id' => $branchId,
            'res_date' => $date,
            'res_time' => $time,
            'party_size' => $partySize,
            'msisdn' => $msisdn,
            'slot_key' => $slotKey,
            'source' => 'whatsapp',
        ];

        try {
            $b = $this->bookings->confirmFromHold($holdRow, [
                'branch_id' => $branchId,

                // keep doctor stable
                'doctor_id' => $doctorId > 0 ? $doctorId : null,

                // ensure res_date/res_time are explicitly passed (safe)
                'res_date' => $date,
                'res_time' => $time,
                'party_size' => $partySize,

                // meta / contact
                'name' => $name,
                'email' => $email,
                'phone' => $msisdn,
                'notes' => $notes,
                'agree_terms' => $agree,
                'locale' => $locale,
                'msisdn' => $msisdn,

                // keep your legacy text (do not rename)
                'party_size_text' => $partyType,

                // source normalization for unified BookingService
                'source' => 'whatsapp',
                // optional:
                // 'source_ref' => (string) $token,
            ]);
        } catch (\RuntimeException $e) {
            // PATCH: if slot got taken between hold and confirm
            if ($e->getMessage() === 'SLOT_TAKEN') {
                $this->ctx->put($token, [
                    'error' => 'SLOT_TAKEN',
                ], $session);

                return $this->respond('SUMMARY', $this->screenSummary($session, $this->ctx->all($token), $locale));
            }

            throw $e;
        } finally {
            $this->holds->releaseByPhoneAndBranch($msisdn, $branchId);
        }

        // QR is no longer sent here — it is dispatched once the user
        // taps "Done" on the CONFIRMATION screen (case 'CONFIRMATION'
        // with $act === 'end_session' below).

        $this->ctx->put($token, [
            'booking_id' => $b->id,
            'booking_code' => $b->booking_code,

            // persist normalized values
            'res_date' => (string) $b->res_date,
            'res_time' => (string) $b->res_time,

            // keep BOTH: numeric + text (add-only)
            'party_size' => $partyType,
            'party_size_int' => (int) ($b->party_size ?? $partySize),
        ], $session);

        $this->ctx->setScreen($token, 'CONFIRMATION');

        return $this->respond('CONFIRMATION', $this->screenConfirmation($session, $this->ctx->all($token), $locale));
    }

    /**
     * Sends the booking QR image (with caption) to the user.
     * Includes idempotency lock so the "finale" webhook trigger and the
     * in-flow "Done" tap do not produce a duplicate send.
     */
    protected function sendBookingConfirmation(Booking $b, WhatsappSession $session): void
    {
        $keyBase = sprintf('wa:confirm:%d:%s', $b->id, $b->booking_code);

        // 10 min lock to prevent double send if user also clicks "Done"
        if (! cache()->add($keyBase, 1, now()->addMinutes(10))) {
            \Log::info('WA confirm skipped (duplicate/lock)', ['booking_id' => $b->id]);

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
            $dateText = (string) $b->res_date;
            $timeText = (string) $b->res_time;
        }

        // 1. Send QR Image
        $qrUrl = route('bookings.qr', ['token' => $this->qr->ensureToken($b)->qr_token]);
        $caption = $this->messages->get('finale.confirm', $locale, [
            'code' => $b->booking_code,
            'datetime' => "{$dateText} • {$timeText}",
            'party' => (string) $b->party_size,
            'pass_url' => $this->qr->passUrl($b),
        ]);

        \Log::info('WA confirm: sending QR', ['booking_id' => $b->id]);

        $sender = new WhatsAppSender($this->waFactory->make());
        if (! $sender->sendImage($session->phone, $qrUrl, $caption)) {
            $sender->sendTextMessage($session->phone, $caption);
        }

        // Note: the booking_confirmed_final / barfres_confirmed template is
        // intentionally not sent — the QR + caption already convey the same
        // information.
    }

    private function buildLocalDateTime(?string $date, ?string $time, string $tz = 'Asia/Kuwait'): ?Carbon
    {
        $date = trim((string) $date);
        $time = trim((string) $time);
        if ($date === '' || $time === '') {
            return null;
        }

        $date = Str::of($date)->before(' ')->value();
        $timeOnly = Str::of($time)->after(' ')->value() ?: $time;
        if (preg_match('/^\d{2}:\d{2}$/', $timeOnly)) {
            $timeOnly .= ':00';
        }

        try {
            return Carbon::parse("{$date} {$timeOnly}", $tz);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function canonAction(array $data): string
    {
        $actions = [
            'clinic_selected', 'branch_selected', 'doctor_selected',
            'party_size_selected', 'date_selected', 'continue_to_details',
            'continue_to_summary', 'confirm_booking', 'go_home', 'view_bookings',
            'edit_datetime', 'edit_party', 'cancel', 'date_changed', 'save_datetime',
            'save_party_size', 'cancel_booking', 'end_session',
        ];

        foreach ($actions as $key) {
            if (! empty($data[$key]) || ($data['trigger'] ?? '') === $key || ($data['action'] ?? '') === $key) {
                return $key;
            }
        }

        return strtolower($data['home_choice'] ?? $data['choice'] ?? '');
    }

    private function respond(string $screen, array $data): array
    {
        return ['screen' => $screen, 'data' => $data];
    }

    private function prefill(?WhatsappSession $session, array $c = []): array
    {
        $contact = $session && $session->phone
            ? \App\Models\WhatsappContact::firstWhere('msisdn', $session->phone)
            : null;

        return [
            'name' => (string) ($c['name'] ?? $contact?->name ?? $session?->name ?? ''),
            'phone' => (string) ($c['phone'] ?? $session?->phone ?? ''),
            'email' => (string) ($c['email'] ?? $contact?->email ?? ''),
        ];
    }

    private function calendarFor(int $branchId, int $party): array
    {
        $branch = Branch::find($branchId);

        $settingDays = (int) config('booking.dates_forward_days', 60);
        $branchDays = (int) ($branch?->max_booking_days ?: 0);

        // choose the larger so changing the setting can extend beyond branch
        $maxDaysInAdvance = max($branchDays, $settingDays, 1);

        $tz = config('app.timezone', 'Asia/Kuwait');
        $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
        $from = $now;
        $to = $now->modify("+{$maxDaysInAdvance} days");

        $minDate = $from->format('Y-m-d');
        $maxDate = $to->format('Y-m-d');

        // pass the resolved days into nextDates()
        $availMap = $this->availability->nextDates($branchId, $maxDaysInAdvance, max(1, $party));
        $availableSet = array_fill_keys(array_keys((array) $availMap), true);

        $unavail = [];
        $period = new \DatePeriod($from, new \DateInterval('P1D'), $to->modify('+1 day'));
        foreach ($period as $day) {
            $d = $day->format('Y-m-d');
            if (! isset($availableSet[$d])) {
                $unavail[] = $d;
            }
        }

        return [$minDate, $maxDate, array_values($unavail)];
    }

    private function firstAvailableDate(string $minDate, string $maxDate, array $unavailable): string
    {
        $bad = array_fill_keys($unavailable, true);
        $start = new \DateTimeImmutable($minDate);
        $end = new \DateTimeImmutable($maxDate);
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));

        foreach ($period as $day) {
            $d = $day->format('Y-m-d');
            if (! isset($bad[$d])) {
                return $d;
            }
        }

        return $minDate; // fallback
    }

    private function upcomingBookingsQuery(string $msisdn)
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $now = now($tz);

        $phones = [
            $msisdn,
            str_starts_with($msisdn, '+') ? substr($msisdn, 1) : "+$msisdn",
        ];

        $contactId = \App\Models\WhatsappContact::whereIn('msisdn', $phones)->value('id');

        return Booking::query()
            ->where(function ($q) use ($phones, $contactId) {
                $q->whereIn('msisdn', $phones);
                if ($contactId) {
                    $q->orWhere('contact_id', $contactId);
                }
            })
            ->whereIn('status', ['confirmed', 'held', '1'])
            ->where(function ($q) use ($now) {
                $q->where('res_date', '>', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->where('res_date', $now->toDateString())
                            ->where('res_time', '>=', $now->format('H:i:s'));
                    });
            })
            ->orderBy('res_date')
            ->orderBy('res_time');
    }

    private function defaultBranchId(): int
    {
        return (int) (
            DB::table('system_settings')->where('key', 'default_branch_id')->value('value')
            ?? \App\Models\Branch::where('is_available', 1)->value('id')
            ?? \App\Models\Branch::value('id')
            ?? 1
        );
    }

    private function loadTerms(int $branchId, string $locale): array
    {
        $t = \App\Models\ReservationTerm::forBranch($branchId)->active()->first();

        $label = $locale === 'ar' ? 'أوافق على شروط الحجز' : 'I agree to the reservation terms';
        $text = '';

        if ($t) {
            $label = $locale === 'ar' ? ($t->label_ar ?? $t->label_en) : ($t->label_en ?? $t->label_ar);
            $text = $locale === 'ar' ? ($t->text_ar ?? $t->text_en) : ($t->text_en ?? $t->text_ar);
        }

        return [
            'terms_id' => $t?->id,
            'terms_required' => (bool) ($t?->terms_required ?? false),
            'terms_label' => $label,
            'terms_text' => $text,
        ];
    }

    private function welcomeHeading(string $name, string $locale): string
    {
        return $locale === 'ar'
            ? ($name ? "مرحبًا {$name}!" : 'مرحبًا!')
            : ($name ? "Welcome {$name}!" : 'Welcome!');
    }

    private function welcomeMessage(string $locale): string
    {
        return $locale === 'ar' ? 'كيف يمكننا مساعدتك اليوم؟' : 'How can we help you today?';
    }

    private function fmtDateTime(string $date, string $time, string $locale): string
    {
        if ($date === '') {
            return '';
        }
        $t = $time ? substr($time, 0, 5) : '';
        $time_formatted = $t ? date('g:i A', strtotime($t)) : '';

        try {
            $date_obj = new \DateTime($date);
            $date_formatted = $date_obj->format('M j, Y'); // e.g., Nov 5, 2025
        } catch (\Exception $e) {
            $date_formatted = $date; // fallback
        }

        if ($locale === 'ar') {
            try {
                $date_formatter = new \IntlDateFormatter(
                    'ar_KW',
                    \IntlDateFormatter::LONG,
                    \IntlDateFormatter::NONE,
                    config('app.timezone', 'Asia/Kuwait')
                );
                $time_formatter = new \IntlDateFormatter(
                    'ar_KW',
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::SHORT,
                    config('app.timezone', 'Asia/Kuwait')
                );
                $date_formatted = $date_formatter->format(strtotime($date));
                $time_formatted = $time_formatter->format(strtotime($t));

                return "{$date_formatted} - {$time_formatted}";
            } catch (\Exception $e) {
                return "{$date} - {$time_formatted}";
            }
        }

        return "{$date_formatted} - {$time_formatted}";
    }

    private function fmtDetailsSummary(array $c, string $locale): string
    {
        $date = (string) ($c['res_date'] ?? '');
        $time = (string) ($c['res_time'] ?? '');
        $party = (string) ($c['party_size'] ?? '');
        $t = substr($time, 0, 5);

        return $locale === 'ar'
            ? "الحجز: {$date} في {$t} لـ {$party}"
            : "Booking: {$date} at {$t} for {$party}";
    }

    private function fmtSummaryReservation(array $c, string $locale): string
    {
        $date = (string) ($c['res_date'] ?? '');
        $time = (string) ($c['res_time'] ?? '');
        $party = (string) ($c['party_size'] ?? '');
        $t = substr($time, 0, 5);

        return $locale === 'ar'
            ? "التاريخ: {$date}\nالوقت: {$t}\nنوع الحجز: {$party}"
            : "Date: {$date}\nTime: {$t}\nType: {$party}";
    }

    private function fmtSummaryContact(array $c, string $locale): string
    {
        $name = (string) ($c['name'] ?? '');
        $phone = (string) ($c['phone'] ?? '');
        $email = (string) ($c['email'] ?? '');
        $notes = (string) ($c['notes'] ?? '');

        if ($locale === 'ar') {
            $lines = ["الاسم: {$name}", "الهاتف: {$phone}"];
            if ($email) {
                $lines[] = "البريد الإلكتروني: {$email}";
            }
            if ($notes) {
                $lines[] = "ملاحظات: {$notes}";
            }

            return implode("\n", $lines);
        }

        $lines = ["Name: {$name}", "Phone: {$phone}"];
        if ($email) {
            $lines[] = "Email: {$email}";
        }
        if ($notes) {
            $lines[] = "Notes: {$notes}";
        }

        return implode("\n", $lines);
    }

    private function fmtConfirmationDetails(array $c, string $locale): string
    {
        $bid = (string) ($c['booking_code'] ?? $c['booking_id'] ?? '');
        $date = (string) ($c['res_date'] ?? '');
        $time = (string) ($c['res_time'] ?? '');
        $party = (string) ($c['party_size'] ?? '');
        $t = substr($time, 0, 5);

        $id_label = $locale === 'ar' ? 'رقم الحجز' : 'Booking ID';
        $date_label = $locale === 'ar' ? 'التاريخ' : 'Date';
        $time_label = $locale === 'ar' ? 'الوقت' : 'Time';
        $party_label = $locale === 'ar' ? 'نوع الحجز' : 'Type';

        return "{$id_label}: {$bid}\n{$date_label}: {$date}\n{$time_label}: {$t}\n{$party_label}: {$party}";
    }

    private function fmtManageBookingTitle(?Booking $b, string $locale): string
    {
        if (! $b) {
            return '';
        }
        $label = $locale === 'ar' ? 'حجز' : 'Booking';
        $who = trim((string) ($b->name ?: $b->msisdn));

        return "{$label} #{$b->id}".($who ? " — {$who}" : '');
    }

    private function fmtManageBookingDetails(?Booking $b, string $locale): string
    {
        if (! $b) {
            return '';
        }
        $t = substr((string) $b->res_time, 0, 5);

        return $locale === 'ar'
            ? "التاريخ: {$b->res_date}\nالوقت: {$t}\nعدد الأفراد: {$b->party_size} أشخاص\nالاسم: {$b->name}\nالهاتف: {$b->msisdn}"
            : "Date: {$b->res_date}\nTime: {$t}\nParty Size: {$b->party_size} people\nName: {$b->name}\nPhone: {$b->msisdn}";
    }

    private function fmtEditAppointmentCurrent(?Booking $b, array $c, string $locale): string
    {
        $date = (string) ($b?->res_date ?? $c['res_date'] ?? '');
        $time = (string) ($b?->res_time ?? $c['res_time'] ?? '');
        $t = substr($time, 0, 5);
        if ($date === '' || $t === '') {
            return '';
        }

        return $locale === 'ar'
            ? "الحالي: {$date} في {$t}"
            : "Current: {$date} at {$t}";
    }

    private function fmtEditSuccessMessage(?Booking $b, array $c, string $locale): string
    {
        $bid = (string) ($b?->id ?? $c['booking_id'] ?? '');
        $date = (string) ($b?->res_date ?? $c['res_date'] ?? '');
        $time = (string) ($b?->res_time ?? $c['res_time'] ?? '');
        $party = (string) ($b?->party_size ?? $c['party_size'] ?? '');
        $t = substr($time, 0, 5);

        return $locale === 'ar'
            ? "تم تحديث حجزك:\n\nرقم الحجز: {$bid}\nالتاريخ: {$date}\nالوقت: {$t}\nعدد الأفراد: {$party} أشخاص"
            : "Your booking has been updated:\n\nBooking ID: {$bid}\nDate: {$date}\nTime: {$t}\nParty Size: {$party} people";
    }

    private function fmtCancelDetails(?Booking $b, string $locale): string
    {
        if (! $b) {
            return '';
        }
        $t = substr((string) $b->res_time, 0, 5);

        return $locale === 'ar'
            ? "رقم الحجز: {$b->id}\nالتاريخ: {$b->res_date}\nالوقت: {$t}\nالعدد: {$b->party_size} أشخاص"
            : "Booking ID: {$b->id}\nDate: {$b->res_date}\nTime: {$t}\nParty: {$b->party_size} people";
    }
}
