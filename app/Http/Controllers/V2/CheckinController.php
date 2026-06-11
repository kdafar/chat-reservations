<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\RestaurantTable;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Support\VisitAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CheckinController extends Controller
{
    use VisitAuthorization;

    /** Reception desk — only reception or admin can use any of these endpoints. */
    protected function abortIfNotReception(): void
    {
        abort_unless($this->canManageBooking(), 403, 'Reception or admin access required.');
    }

    public function index(): Response
    {
        $this->abortIfNotReception();

        return Inertia::render('Checkin/Index');
    }

    /**
     * Step 1 — find a booking ready for check-in.
     * Searches today's confirmed/pending bookings by code, msisdn, or
     * patient name. Returns matches scoped to whatever branches the user
     * has access to (BelongsToBranchScope on Booking handles this).
     */
    public function search(Request $request): JsonResponse
    {
        $this->abortIfNotReception();
        $q = trim((string) $request->query('q', ''));

        $query = Booking::query()
            ->with(['patient', 'doctor', 'branch'])
            ->whereDate('res_date', today())
            ->whereIn('status', ['confirmed', 'pending']);

        if (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('booking_code', 'like', $like)
                    ->orWhere('msisdn', 'like', $like)
                    // Also match the patient's *current* phone — the booking's
                    // msisdn can be stale if the patient's number changed since.
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like));
            });
        }

        $rows = $query->orderBy('res_time')->limit(20)->get();

        return response()->json([
            'items' => $rows->map(fn (Booking $b) => $this->summarizeBooking($b))->values(),
        ]);
    }

    /**
     * Step 2 — full booking + payment + room status for the wizard.
     */
    public function booking(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();
        $booking->load(['patient', 'doctor', 'branch']);

        $paid = (float) VisitPayment::query()
            ->whereIn('visit_id', Visit::where('booking_id', $booking->id)->pluck('id'))
            ->where('kind', VisitPayment::KIND_CONSULTATION)
            ->where('status', 'paid')
            ->sum('amount');

        $fee = (float) ($booking->doctor->consultation_fee ?? 0);

        return response()->json([
            'booking' => $this->summarizeBooking($booking, [
                'fee' => $fee,
                'paid_consultation' => $paid,
                'consultation_paid' => $fee > 0 && $paid >= $fee,
                'already_checked_in' => ! is_null($booking->checked_in_at),
            ]),
            // Manual desk methods only (cash/KNET/card/transfer). Insurance is a
            // claim/coverage concept — never a fee paid at reception — and online
            // is handled separately via the payment-link / QR action below.
            'payment_methods' => $this->consultationMethods($booking),
            // Whether a MyFatoorah gateway is configured for this branch, so the
            // fee step can offer an online payment link / QR (mirrors VisitSheet).
            'online_payment_available' => $this->onlinePaymentAvailable($booking),
        ]);
    }

    /**
     * Manual payment methods valid for collecting a consultation fee at the desk:
     * the configured set minus online (link) and minus insurance. Online is its
     * own link/QR flow; insurance is the claim module, not a cash-drawer method.
     *
     * @return array<int, array{key:string,label:string,type:string,requires_reference:bool}>
     */
    protected function consultationMethods(Booking $booking): array
    {
        $methods = app(\App\Services\Clinic\ClinicPaymentMethodResolver::class)
            ->forBranchOrDefault((int) $booking->branch_id, (int) ($booking->branch?->partner_id ?? 0));

        return array_values(array_filter(
            $methods,
            fn ($m) => ($m['type'] ?? 'manual') !== 'online' && ($m['key'] ?? '') !== 'insurance',
        ));
    }

    /** Is a MyFatoorah gateway configured for this booking's branch? */
    protected function onlinePaymentAvailable(Booking $booking): bool
    {
        return (bool) \App\Models\GatewayAccount::bestForBranch(
            (int) $booking->branch_id,
            (int) ($booking->branch?->partner_id ?? 0) ?: null,
        );
    }

    /**
     * Step 2 — collect the consultation fee.
     * Creates a VisitPayment(kind=consultation, status=paid). Auto-creates
     * the Visit shell first if it doesn't exist yet, since payments need a
     * visit_id.
     */
    public function collectFee(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        // Validate against the manual desk methods (cash/KNET/card/transfer) —
        // online + insurance are excluded (see consultationMethods()).
        $methods = $this->consultationMethods($booking);
        $allowedKeys = array_values(array_filter(array_column($methods, 'key')));

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.001',
            'method' => ['required', \Illuminate\Validation\Rule::in($allowedKeys)],
            'reference_no' => 'nullable|string|max:64',
        ]);

        // Card / KNET / transfer require a transaction/reference id — cash
        // doesn't. Enforce server-side per the method's config flag.
        $chosen = collect($methods)->firstWhere('key', $data['method']);
        if (($chosen['requires_reference'] ?? false) && trim((string) ($data['reference_no'] ?? '')) === '') {
            return response()->json([
                'ok' => false,
                'error' => 'A transaction / reference number is required for '.($chosen['label'] ?? $data['method']).' payments.',
                'field' => 'reference_no',
            ], 422);
        }

        $fee = (float) ($booking->doctor->consultation_fee ?? 0);
        if ($fee <= 0) {
            return response()->json(['ok' => false, 'error' => 'Doctor has no consultation fee set.'], 422);
        }

        DB::transaction(function () use ($booking, $data, $fee) {
            $visit = $this->ensureVisitWithFeeCharge($booking, $fee);

            VisitPayment::create([
                'visit_id' => $visit->id,
                'amount' => (float) $data['amount'],
                'method' => (string) $data['method'],
                'reference_no' => $data['reference_no'] ?? null,
                'status' => 'paid',
                'kind' => VisitPayment::KIND_CONSULTATION,
                'collected_by_user_id' => auth()->id(),
                'paid_at' => now(),
            ]);
        });

        return $this->booking($request, $booking);
    }

    /**
     * Get-or-create the Visit shell for a booking and ensure its
     * 'Consultation Fee' VisitCharge exists. Shared by collectFee() and the
     * payment-link flow — both need the visit + charge before money can land
     * against it (the check-in "hasCharge" gate depends on this row).
     */
    protected function ensureVisitWithFeeCharge(Booking $booking, float $fee): Visit
    {
        $visit = Visit::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'patient_id' => $booking->patient_id,
                'doctor_id' => $booking->doctor_id,
                'branch_id' => $booking->branch_id,
                'restaurant_table_id' => $booking->table_id,
                'source' => $booking->source,
                'booking_code' => $booking->booking_code,
                'status' => Visit::STATUS_CREATED,
            ]
        );

        \App\Models\VisitCharge::updateOrCreate(
            ['visit_id' => $visit->id, 'label' => 'Consultation Fee'],
            [
                'branch_id' => (int) $visit->branch_id,
                'qty' => 1,
                'unit_price_snapshot' => $fee,
                'line_total' => $fee,
                'added_by_user_id' => auth()->id(),
            ]
        );

        return $visit;
    }

    /**
     * Generate a MyFatoorah payment link + QR for the consultation fee. The
     * VisitPayment is recorded by the gateway callback once paid — never here —
     * so this is safe to call repeatedly. Mirrors VisitConsole's payment-link.
     */
    public function createPaymentLink(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        if (! $this->onlinePaymentAvailable($booking)) {
            return response()->json(['ok' => false, 'error' => 'Online payment is not configured for this branch.'], 422);
        }

        $fee = (float) ($booking->doctor->consultation_fee ?? 0);
        if ($fee <= 0) {
            return response()->json(['ok' => false, 'error' => 'Doctor has no consultation fee set.'], 422);
        }

        try {
            $visit = DB::transaction(fn () => $this->ensureVisitWithFeeCharge($booking, $fee));
            $res = app(\App\Services\Clinic\VisitPaymentLinkService::class)
                ->createForVisit($visit, $fee, 'consultation');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $res);
    }

    /**
     * Generate the consultation-fee payment link and push it to the patient's
     * WhatsApp. Prefers the approved `clinic_payment_link` template (works
     * outside the 24h window); falls back to a plain session message.
     */
    public function sendPaymentLinkWhatsApp(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        if (! $this->onlinePaymentAvailable($booking)) {
            return response()->json(['ok' => false, 'error' => 'Online payment is not configured for this branch.'], 422);
        }

        $fee = (float) ($booking->doctor->consultation_fee ?? 0);
        if ($fee <= 0) {
            return response()->json(['ok' => false, 'error' => 'Doctor has no consultation fee set.'], 422);
        }

        $phone = (string) ($booking->patient?->phone ?? $booking->msisdn ?? '');
        if (trim($phone) === '') {
            return response()->json(['ok' => false, 'error' => 'No phone number on file for this patient.'], 422);
        }

        try {
            $visit = DB::transaction(fn () => $this->ensureVisitWithFeeCharge($booking, $fee));
            $res = app(\App\Services\Clinic\VisitPaymentLinkService::class)
                ->createForVisit($visit, $fee, 'consultation');
            $url = $res['url'];
            $amount = (float) $res['amount'];

            $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
            $wa = app(\App\Wa\Services\WhatsApp\WhatsAppService::class);

            if ($this->paymentTemplateApproved($locale)) {
                $name = $booking->patient?->name ?: ($locale === 'ar' ? 'عميلنا' : 'there');
                $clinic = $booking->branch?->getTranslation('name', $locale, true) ?: config('app.name', 'Our Clinic');
                $appointment = $this->paymentApptText($booking, $locale);
                $amountText = number_format($amount, 3).($locale === 'ar' ? ' د.ك' : ' KWD');

                $wa->sendClinicPaymentLink($phone, $locale, $name, $clinic, $appointment, $amountText, $url);

                return response()->json(['ok' => true, 'via' => 'template']);
            }

            $name = $booking->patient?->name ?: '';
            $msg = $locale === 'ar'
                ? trim("مرحباً {$name}، يرجى إتمام دفع رسوم الاستشارة".($amount ? ' بمبلغ '.number_format($amount, 3).' د.ك' : '').' عبر الرابط: '.$url)
                : trim("Hello {$name}, please complete your consultation fee payment".($amount ? ' of '.number_format($amount, 3).' KWD' : '').' here: '.$url);

            $sent = $wa->sendTextMessage($phone, $msg);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if (! $sent) {
            return response()->json([
                'ok' => false,
                'soft' => true,
                'error' => 'Could not send on WhatsApp (patient may be outside the 24-hour window, and the payment-link template is not approved yet). The link is still available to copy or scan.',
            ], 422);
        }

        return response()->json(['ok' => true, 'via' => 'text']);
    }

    /** Is the payment-link WhatsApp template approved on Meta for this language? */
    protected function paymentTemplateApproved(string $locale): bool
    {
        $name = config('services.whatsapp.templates.payment_link', 'clinic_payment_link');

        try {
            return \App\Wa\Hub\Models\MessageTemplate::query()
                ->where('name', $name)
                ->where('status', 'APPROVED')
                ->where(fn ($q) => $q->where('language', $locale)->orWhereNull('language'))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Locale-aware "when" string for the payment template's appointment slot. */
    protected function paymentApptText(Booking $booking, string $locale): string
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $dt = null;

        if (trim((string) $booking->res_date) !== '') {
            try {
                $date = str_replace('/', '-', \Illuminate\Support\Str::of((string) $booking->res_date)->before(' ')->value());
                $time = trim((string) $booking->res_time) !== '' ? trim((string) $booking->res_time) : '00:00';
                $dt = Carbon::parse("{$date} {$time}", $tz);
            } catch (\Throwable $e) {
                $dt = null;
            }
        }

        if (! $dt) {
            return $locale === 'ar' ? 'زيارتك' : 'your visit';
        }

        $dt = $dt->setTimezone($tz)->locale($locale);

        return $locale === 'ar'
            ? $dt->isoFormat('dddd D MMMM، h:mm a')
            : $dt->isoFormat('ddd, MMM D [at] h:mm A');
    }

    /**
     * Step 3 — rooms for assignment.
     *
     * Returns only AVAILABLE rooms in the booking's branch. The doctor's
     * default room (Doctor.restaurant_table_id) is returned separately so
     * the frontend can flag/pre-select it. If that room is currently
     * occupied, `doctor_room_busy: true` is set so the receptionist sees
     * a hint instead of being silently switched away.
     */
    public function rooms(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        if (! $booking->branch_id) {
            return response()->json(['rooms' => [], 'doctor_room_id' => null, 'doctor_room_busy' => false, 'doctor_room_name' => null]);
        }

        $all = RestaurantTable::query()
            ->where('branch_id', $booking->branch_id)
            ->orderBy('name')
            ->get(['id', 'name', 'capacity', 'status']);

        $available = $all->filter(fn ($r) => $r->status === 'available');

        $doctorRoomId = $booking->doctor?->restaurant_table_id;
        $doctorRoom = $doctorRoomId ? $all->firstWhere('id', $doctorRoomId) : null;
        $doctorRoomBusy = $doctorRoom ? ($doctorRoom->status !== 'available') : false;

        return response()->json([
            'rooms' => $available->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'capacity' => $r->capacity,
                'status' => $r->status,
                'available' => true,
                'is_doctor_room' => $doctorRoomId && (int) $r->id === (int) $doctorRoomId,
            ])->sortByDesc('is_doctor_room')->values(),
            'doctor_room_id' => $doctorRoomId,
            'doctor_room_name' => $doctorRoom?->name,
            'doctor_room_busy' => $doctorRoomBusy,
        ]);
    }

    /**
     * Step 3 — final check-in.
     * Mirrors the strict-paid-before-checkin policy from the Filament path.
     * Marks the room occupied, sets booking.checked_in_at + status=confirmed,
     * upserts the Visit with status=awaiting_doctor.
     */
    public function checkin(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        $request->validate([
            'room_id' => 'nullable|integer|exists:restaurant_tables,id',
        ]);

        // Match Filament BookingResource: refuse if booking already in a
        // terminal state, or if it's already checked in.
        if (in_array($booking->status, ['cancelled', 'no_show', 'completed'], true)) {
            return response()->json(['ok' => false, 'error' => 'Booking is closed and cannot be checked in.'], 422);
        }
        if (! is_null($booking->checked_in_at)) {
            return response()->json(['ok' => false, 'error' => 'Booking is already checked in.'], 422);
        }

        // Match Filament's two-gate consultation check:
        //   1. VisitCharge with label='Consultation Fee' must exist (invoice raised)
        //   2. VisitPayment(kind=consultation, status=paid) > 0 must exist (money in)
        $visitId = Visit::query()->where('booking_id', $booking->id)->value('id');
        $fee = (float) ($booking->doctor->consultation_fee ?? 0);

        if ($fee > 0) {
            $hasCharge = $visitId
                ? \App\Models\VisitCharge::query()
                    ->where('visit_id', $visitId)
                    ->where('label', 'Consultation Fee')
                    ->exists()
                : false;

            if (! $hasCharge) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Consultation fee must be collected before check-in.',
                ], 422);
            }

            $paid = $visitId
                ? (float) VisitPayment::query()
                    ->where('visit_id', $visitId)
                    ->where('kind', VisitPayment::KIND_CONSULTATION)
                    ->where('status', 'paid')
                    ->sum('amount')
                : 0.0;

            if ($paid <= 0.0) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Consultation fee must be paid before check-in.',
                ], 422);
            }
        }

        $roomId = $request->input('room_id');

        try {
            $visit = DB::transaction(function () use ($booking, $roomId) {
                $now = now();

                // Lock the booking so two reception clicks can't double-check-in.
                /** @var Booking $fresh */
                $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                if (! is_null($fresh->checked_in_at)) {
                    throw new \RuntimeException('Booking is already checked in.');
                }

                if ($roomId) {
                    $room = RestaurantTable::lockForUpdate()->find($roomId);
                    if (! $room) {
                        throw new \RuntimeException('Room not found.');
                    }
                    $room->update(['status' => 'occupied']);
                }

                $fresh->update([
                    'checked_in_at' => $now,
                    'status' => Booking::S_CONFIRMED,
                    'table_id' => $roomId ?: $fresh->table_id,
                ]);

                $visit = Visit::firstOrNew(['booking_id' => $fresh->id]);
                $visit->fill([
                    'patient_id' => $fresh->patient_id,
                    'doctor_id' => $fresh->doctor_id,
                    'branch_id' => $fresh->branch_id,
                    'restaurant_table_id' => $roomId ?: $visit->restaurant_table_id,
                    'source' => $fresh->source,
                    'booking_code' => $fresh->booking_code,
                    'checked_in_at' => $visit->checked_in_at ?? $now,
                    'queued_at' => $visit->queued_at ?? $now,
                ]);

                if (! in_array(($visit->status ?? null), ['completed', 'no_show', 'cancelled'], true)) {
                    $visit->status = Visit::STATUS_AWAITING_DOCTOR;
                }

                $visit->save();

                return $visit;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'visit_id' => $visit->id,
            'visit_url' => '/admin/v2/visits/'.$visit->id,
        ]);
    }

    protected function summarizeBooking(Booking $b, array $extra = []): array
    {
        return array_merge([
            'id' => $b->id,
            'booking_code' => $b->booking_code,
            'status' => $b->status,
            'res_date' => optional($b->res_date)->toDateString(),
            'res_time' => $b->res_time,
            'checked_in_at' => optional($b->checked_in_at)->toIso8601String(),
            'table_id' => $b->table_id,
            'patient' => $b->patient ? [
                'id' => $b->patient->id,
                'name' => $b->patient->name,
                'msisdn' => $b->patient->phone ?? null,
            ] : null,
            'doctor' => $b->doctor ? [
                'id' => $b->doctor->id,
                'name' => $b->doctor->name,
                'consultation_fee' => (float) ($b->doctor->consultation_fee ?? 0),
            ] : null,
            'branch' => $b->branch ? [
                'id' => $b->branch->id,
                'name' => $b->branch->getTranslation('name', app()->getLocale(), true),
            ] : null,
            // Identity review (Phase 3): present when this booking's phone
            // matched an existing patient under a *different* name. Reception
            // resolves it at check-in via confirm-identity / split-patient.
            'identity_review' => $this->identityReviewPayload($b),
        ], $extra);
    }

    /**
     * Returns the booking's pending identity-review hint, or null. Reads
     * booking.meta['identity_review'] (set by BookingService when a phone
     * matched an existing patient but the incoming name differed).
     */
    protected function identityReviewPayload(Booking $b): ?array
    {
        $meta = $b->meta;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        $review = is_array($meta) ? ($meta['identity_review'] ?? null) : null;
        if (! is_array($review) || empty($review['matched_patient_id'])) {
            return null;
        }

        return [
            'matched_patient_id' => (int) $review['matched_patient_id'],
            'matched_patient_name' => (string) ($review['matched_patient_name'] ?? ''),
            'proposed_name' => (string) ($review['proposed_name'] ?? ''),
            'phone' => (string) ($review['phone'] ?? ($b->msisdn ?? '')),
        ];
    }

    /**
     * Identity review — CONFIRM. The phone really does belong to the existing
     * patient; keep the link as-is and just clear the review flag.
     */
    public function confirmIdentity(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        $this->clearIdentityReview($booking);

        return $this->booking($request, $booking);
    }

    /**
     * Identity review — SPLIT (it's a new person). The number was reassigned:
     * create a NEW patient from the booking's proposed name + phone, repoint
     * the booking (and its visit, if one exists) to the new patient, then
     * clear the flag.
     */
    public function splitPatient(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        $review = $this->identityReviewPayload($booking);
        if (! $review) {
            // Nothing to split (already resolved) — just return current state.
            return $this->booking($request, $booking);
        }

        $name = trim($review['proposed_name']) !== '' ? trim($review['proposed_name']) : trim($review['phone']);
        $phone = (string) $review['phone'];

        try {
            DB::transaction(function () use ($booking, $name, $phone) {
                /** @var Booking $fresh */
                $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                $data = ['name' => $name, 'phone' => $phone];
                // Inherit partner scoping from the existing patient / branch so
                // the new patient lands in the same clinic.
                if (\Illuminate\Support\Facades\Schema::hasColumn('patients', 'partner_id')) {
                    $partnerId = $fresh->patient?->partner_id ?? $fresh->branch?->partner_id;
                    if ($partnerId) {
                        $data['partner_id'] = $partnerId;
                    }
                }

                $newPatient = \App\Models\Patient::create($data);

                // Repoint the booking.
                $fresh->patient_id = $newPatient->id;
                $meta = is_array($fresh->meta) ? $fresh->meta : [];
                unset($meta['identity_review']);
                $fresh->meta = $meta;
                $fresh->save();

                // Repoint any visit already created for this booking.
                Visit::query()
                    ->where('booking_id', $fresh->id)
                    ->update(['patient_id' => $newPatient->id]);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'Could not create the new patient.'], 422);
        }

        return $this->booking($request, $booking->refresh());
    }

    /** Remove the identity_review hint from booking.meta (idempotent). */
    protected function clearIdentityReview(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (array_key_exists('identity_review', $meta)) {
            unset($meta['identity_review']);
            $booking->meta = $meta;
            $booking->save();
        }
    }
}
