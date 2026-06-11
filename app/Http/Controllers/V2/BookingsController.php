<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Support\ResolvesAccessibleClinics;
use App\Support\VisitAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookingsController extends Controller
{
    use VisitAuthorization;
    use ResolvesAccessibleClinics;

    /** Canonical booking source vocabulary — shared with the Filament admin. */
    public const SOURCES = ['web', 'whatsapp', 'call', 'walk_in', 'reception'];

    /** Reception desk role required to mutate bookings. */
    protected function abortIfNotReception(): void
    {
        abort_unless($this->canManageBooking(), 403, 'Reception or admin access required.');
    }

    /** Doctors for the form — carry branch + fee + their assigned room (for auto-room). */
    protected function doctorOptions(): \Illuminate\Support\Collection
    {
        return Doctor::query()->orderBy('name')
            ->get(['id', 'name', 'branch_id', 'consultation_fee', 'restaurant_table_id'])
            ->map(fn (Doctor $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'branch_id' => $d->branch_id,
                'consultation_fee' => (float) ($d->consultation_fee ?? 0),
                'restaurant_table_id' => $d->restaurant_table_id,
            ])->values();
    }

    /** Globally-unique MAN- booking code (booking_code has a unique index). */
    protected function uniqueManualBookingCode(): string
    {
        do {
            $code = 'MAN-'.strtoupper(Str::random(6));
        } while (Booking::withoutGlobalScopes()->where('booking_code', $code)->exists());

        return $code;
    }

    /** Rooms for the branch→room cascade (branch-scoped automatically). */
    protected function roomOptions(): \Illuminate\Support\Collection
    {
        return \App\Models\RestaurantTable::query()->orderBy('name')
            ->get(['id', 'name', 'branch_id'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'branch_id' => $r->branch_id])
            ->values();
    }

    /** Stream selected bookings as CSV (bulk export). Not an Inertia response. */
    public function export(Request $request)
    {
        $this->abortIfNotReception();
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $query = Booking::query()->with(['patient:id,name,phone', 'doctor:id,name', 'branch:id,name'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderByDesc('id');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query,
                ['ID', 'Date', 'Time', 'Code', 'Patient', 'Phone', 'Doctor', 'Branch', 'Source', 'Status'],
                fn ($b) => [
                    $b->id, (string) $b->res_date, (string) $b->res_time, $b->booking_code,
                    $b->patient?->name, $b->patient?->phone ?? $b->msisdn,
                    $b->doctor?->name, $b->branch?->localized_name, $b->source, $b->status,
                ],
                'Bookings',
                app()->getLocale() === 'ar',
            ),
            'bookings-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function index(Request $request): Response
    {
        $this->abortIfNotReception();
        $filters = $this->normalizeFilters($request);

        // Reference data for filter dropdowns. Map to plain arrays so the
        // Spatie HasTranslations field doesn't leak as raw JSON on the wire.
        $locale = app()->getLocale();
        $branches = Branch::query()->orderBy('name')->get()
            ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->getTranslation('name', $locale, true)])
            ->values();
        $doctors  = Doctor::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Doctor $d) => ['id' => $d->id, 'name' => $d->name])
            ->values();

        return Inertia::render('Bookings/Index', [
            'filters' => $filters,
            'branches' => $branches,
            'doctors' => $doctors,
            'page' => $this->queryBookings($filters),
            'counts' => $this->statusCounts($filters),
        ]);
    }

    /** Lazy JSON refresh for partial reloads. */
    public function list(Request $request): JsonResponse
    {
        $this->abortIfNotReception();
        $filters = $this->normalizeFilters($request);

        return response()->json([
            'page' => $this->queryBookings($filters),
            'counts' => $this->statusCounts($filters),
        ]);
    }

    /** Full booking detail for the quick-view sheet. */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();
        $booking->load(['patient', 'doctor', 'branch']);

        $visit = Visit::query()->where('booking_id', $booking->id)->first();
        $fee = (float) ($booking->doctor->consultation_fee ?? 0);
        $paid = 0.0;
        if ($visit) {
            $paid = (float) VisitPayment::query()
                ->where('visit_id', $visit->id)
                ->where('kind', VisitPayment::KIND_CONSULTATION)
                ->where('status', 'paid')
                ->sum('amount');
        }

        return response()->json([
            'booking' => $this->transformBooking($booking, [
                'fee_amount' => $fee,
                'paid_consultation' => $paid,
                'consultation_paid' => $fee > 0 && $paid >= $fee,
                'visit_id' => $visit?->id,
                'visit_status' => $visit?->status,
                'notes' => $booking->notes,
                'cancel_reason' => $booking->cancel_reason ?? null,
                'meta' => $booking->meta ?? null,
            ]),
            // Manual desk methods only (cash/KNET/card/transfer) — insurance and
            // online aren't fee-at-reception methods. Same set as the check-in wizard.
            'payment_methods' => $this->consultationMethods($booking),
        ]);
    }

    /**
     * Manual payment methods valid for collecting a consultation fee at the desk:
     * the configured set minus online (link) and minus insurance.
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

    /**
     * Render the v2 "New booking" Inertia page. Ships the form's reference
     * data (locale-aware branch names, doctors with consultation fee, sources)
     * and the first slice of patients for the SearchableSelect.
     */
    public function create(Request $request): Response
    {
        $this->abortIfNotReception();

        $branches = $this->accessibleBranches();
        $doctors = $this->doctorOptions();
        $rooms = $this->roomOptions();

        // First slice of patients for the picker. SearchableSelect filters
        // these client-side; for long tails the global search palette
        // covers the rest.
        $patients = Patient::query()
            ->orderByDesc('id')
            ->limit(60)
            ->get(['id', 'name', 'phone', 'civil_id'])
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $p->phone,
                'civil_id' => $p->civil_id,
            ])
            ->values();

        return Inertia::render('Bookings/Create', [
            'branches' => $branches,
            'doctors' => $doctors,
            'rooms' => $rooms,
            'patients' => $patients,
            'sources' => self::SOURCES,
        ]);
    }

    /**
     * JSON form options for the NewBookingSheet slide-over. Same shape as
     * `create()` minus the patient seed list (the sheet uses the global
     * search palette / pre-selected patient instead).
     */
    public function formOptions(Request $request): JsonResponse
    {
        $this->abortIfNotReception();

        // Seed list for the patient picker. The SearchableSelect filters
        // client-side; the global ⌘K palette covers the long tail.
        $patients = Patient::query()
            ->orderByDesc('id')
            ->limit(80)
            ->get(['id', 'name', 'phone', 'civil_id'])
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $p->phone,
                'civil_id' => $p->civil_id,
            ])->values();

        return response()->json([
            'ok' => true,
            'branches' => $this->accessibleBranches(),
            'doctors' => $this->doctorOptions(),
            'rooms' => $this->roomOptions(),
            'patients' => $patients,
            'sources' => self::SOURCES,
        ]);
    }

    /**
     * Available time-slots for a given doctor/date. Reads the doctor's
     * `working_hours` JSON (array of { day, start, end }), filters to entries
     * matching the requested date's day-of-week, expands each window into
     * `slot_minutes`-sized intervals, and removes any slot already taken by
     * a pending/confirmed booking.
     */
    public function slots(Request $request): JsonResponse
    {
        $this->abortIfNotReception();
        $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'date' => 'required|date_format:Y-m-d',
            'slot_minutes' => 'nullable|integer|min:5|max:240',
        ]);

        $doctorId = (int) $request->input('doctor_id');
        $date = $request->input('date');
        $slotMinutes = (int) $request->input('slot_minutes', 15);

        $doctor = Doctor::query()->find($doctorId);
        if (! $doctor) {
            return response()->json(['slots' => []]);
        }

        $hours = is_array($doctor->working_hours) ? $doctor->working_hours : [];
        $dow = (int) Carbon::parse($date)->dayOfWeek; // 0=Sun..6=Sat

        $candidates = [];
        foreach ($hours as $w) {
            if (! is_array($w)) continue;
            if ((int) ($w['day'] ?? -1) !== $dow) continue;
            $start = (string) ($w['start'] ?? '');
            $end = (string) ($w['end'] ?? '');
            if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) continue;

            $cursor = Carbon::createFromFormat('H:i', $start);
            $stop = Carbon::createFromFormat('H:i', $end);
            while ($cursor->lt($stop)) {
                $candidates[] = $cursor->format('H:i');
                $cursor->addMinutes($slotMinutes);
            }
        }

        // De-dupe in case overlapping windows generate the same slot.
        $candidates = array_values(array_unique($candidates));

        // Strip taken slots.
        $taken = Booking::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('res_date', $date)
            ->whereIn('status', [Booking::S_CONFIRMED, Booking::S_PENDING])
            ->pluck('res_time')
            ->map(fn ($t) => $t ? substr((string) $t, 0, 5) : null)
            ->filter()
            ->values()
            ->all();

        $slots = array_values(array_diff($candidates, $taken));
        sort($slots);

        return response()->json(['slots' => $slots]);
    }

    /**
     * Store a new booking (manually-created from the v2 admin). Optionally
     * creates a new Patient inline if `patient_id` isn't supplied.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->abortIfNotReception();
        $data = $request->validate([
            'patient_id' => 'nullable|integer|exists:patients,id',
            'new_patient.name' => 'required_without:patient_id|string|max:255',
            'new_patient.phone' => 'nullable|string|max:32',
            'new_patient.gender' => 'nullable|in:male,female',
            'new_patient.civil_id' => 'nullable|string|max:32',
            'branch_id' => 'required|integer|exists:branches,id',
            'doctor_id' => 'required|integer|exists:doctors,id',
            'res_date' => 'required|date_format:Y-m-d',
            'res_time' => 'required|date_format:H:i',
            'party_size' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:2000',
            // Canonical source vocabulary — same set as the Filament admin so
            // reports group consistently across both UIs + the WhatsApp webhook.
            'source' => 'nullable|string|in:web,whatsapp,call,walk_in,reception',
            // Optional room, must belong to the chosen branch (branch→room cascade).
            'table_id' => ['nullable', 'integer', Rule::exists('restaurant_tables', 'id')->where('branch_id', (int) $request->input('branch_id'))],
        ]);

        $booking = DB::transaction(function () use ($data, $request) {
            $branch = Branch::query()->find($data['branch_id']);

            $patientId = $data['patient_id'] ?? null;
            $patientPhone = null;

            if ($patientId) {
                $existing = Patient::query()->find($patientId);
                $patientPhone = $existing?->phone;
            } else {
                $np = (array) $request->input('new_patient', []);
                $payload = [
                    'name' => trim((string) ($np['name'] ?? '')),
                    'phone' => trim((string) ($np['phone'] ?? '')) ?: null,
                    'gender' => $np['gender'] ?? null,
                    'civil_id' => trim((string) ($np['civil_id'] ?? '')) ?: null,
                ];
                if (Schema::hasColumn('patients', 'partner_id') && ! empty($branch?->partner_id)) {
                    $payload['partner_id'] = $branch->partner_id;
                }
                $patient = Patient::create(array_filter($payload, fn ($v) => $v !== null));
                $patientId = $patient->id;
                $patientPhone = $patient->phone;
            }

            $source = (string) ($data['source'] ?? 'reception');

            // Room: explicit choice, else auto-assign the doctor's own room
            // (mirrors the old admin's auto-room-from-doctor behaviour).
            $tableId = $data['table_id'] ?? null;
            if (! $tableId) {
                $tableId = Doctor::query()->whereKey($data['doctor_id'])->value('restaurant_table_id');
            }

            // Link to a WhatsApp contact by phone, if one exists — so booking
            // reminders/threads attach to the right contact (old admin links
            // contact_id; we resolve it server-side from the phone).
            $contactId = null;
            $digits = preg_replace('/\D/', '', (string) $patientPhone);
            if ($digits && \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'contact_id')) {
                $contactId = \App\Models\WhatsappContact::query()
                    ->where('msisdn', $patientPhone)
                    ->when(strlen($digits) >= 8, fn ($q) => $q->orWhere('msisdn', 'LIKE', "%{$digits}"))
                    ->value('id');
            }

            // res_start / res_end MUST be set for slot-blocking, the no-show
            // sweep and overlap detection to work. The Filament form derives
            // them on save; mirror that here so v2-created bookings aren't
            // invisible to those code paths.
            [$resStart, $resEnd] = $this->deriveSlotWindow(
                $data['res_date'],
                $data['res_time'],
                (int) $data['branch_id'],
            );

            return Booking::create([
                'patient_id' => $patientId,
                'branch_id' => $data['branch_id'],
                'doctor_id' => $data['doctor_id'],
                'table_id' => $tableId,
                'contact_id' => $contactId,
                'res_date' => $data['res_date'],
                'res_time' => $data['res_time'],
                'res_start' => $resStart,
                'res_end' => $resEnd,
                'party_size' => $data['party_size'],
                'notes' => $data['notes'] ?? null,
                'status' => Booking::S_CONFIRMED,
                'booking_code' => $this->uniqueManualBookingCode(),
                'source' => $source,
                // msisdn is NOT NULL in the DB; fall back to '' (as BookingService
                // does) when the patient has no phone on file.
                'msisdn' => $patientPhone ?: '',
            ]);
        });

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'booking' => [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'patient_id' => $booking->patient_id,
                    'doctor_id' => $booking->doctor_id,
                    'branch_id' => $booking->branch_id,
                    'res_date' => $booking->res_date,
                    'res_time' => $booking->res_time,
                ],
            ]);
        }

        return redirect('/admin/v2/bookings')
            ->with('success', 'Booking '.$booking->booking_code.' created.');
    }

    /**
     * Cancel a booking. Mirrors the Filament path:
     *   - reject if already checked-in
     *   - reject if already in a terminal state
     *   - set status to cancelled
     */
    /** Edit booking details (notes / status / source / party size / doctor). */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        $data = $request->validate([
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'status' => ['nullable', \Illuminate\Validation\Rule::in([
                Booking::S_PENDING, Booking::S_CONFIRMED, Booking::S_CANCELLED, Booking::S_COMPLETED,
            ])],
            'source' => ['nullable', 'string', 'max:32'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Keep the doctor within the booking's branch.
        if (! empty($data['doctor_id'])) {
            $ok = \App\Models\Doctor::where('id', $data['doctor_id'])->where('branch_id', $booking->branch_id)->exists();
            if (! $ok) {
                return response()->json(['ok' => false, 'error' => 'That doctor is not in this booking\'s branch.'], 422);
            }
        }

        $update = [];
        if ($request->has('doctor_id')) $update['doctor_id'] = $data['doctor_id'] ?? null;
        if (! empty($data['status'])) $update['status'] = $data['status'];
        if ($request->has('source')) $update['source'] = $data['source'] ?? null;
        if (! empty($data['party_size'])) $update['party_size'] = $data['party_size'];
        if ($request->has('notes')) $update['notes'] = $data['notes'];
        $booking->update($update);

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        if ($booking->checked_in_at) {
            return response()->json([
                'ok' => false,
                'error' => 'Cannot cancel a booking that has already been checked in.',
            ], 422);
        }
        if (in_array($booking->status, [Booking::S_CANCELLED, Booking::S_COMPLETED, Booking::S_NO_SHOW], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'Booking is already in a terminal state.',
            ], 422);
        }

        $booking->update([
            'status' => Booking::S_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancel_reason' => (string) $request->input('reason', ''),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Mark booking as no-show. Mirrors Filament's guard rails: must be
     * confirmed, not checked-in, not already terminal, and the reserved
     * end-time must be in the past (so we don't mark future bookings as
     * no-show by mistake).
     */
    public function markNoShow(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();
        if (! in_array($booking->status, [Booking::S_CONFIRMED, Booking::S_PENDING], true)) {
            return response()->json(['ok' => false, 'error' => 'Booking is not in a state that can be marked no-show.'], 422);
        }
        if ($booking->checked_in_at) {
            return response()->json(['ok' => false, 'error' => 'Cannot mark a checked-in booking as no-show.'], 422);
        }
        if ($booking->res_end && Carbon::parse($booking->res_end)->isFuture()) {
            return response()->json(['ok' => false, 'error' => 'Cannot mark no-show before the booking end time.'], 422);
        }

        DB::transaction(function () use ($booking) {
            $now = now();
            $meta = (array) ($booking->meta ?? []);
            $meta['closed_at'] = $now->toDateTimeString();

            $booking->update([
                'status' => Booking::S_NO_SHOW,
                'no_show_at' => $now,
                'meta' => $meta,
            ]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Rooms available for assignment, scoped to the booking's branch.
     */
    public function rooms(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();
        if (! $booking->branch_id) return response()->json(['rooms' => []]);

        $rooms = \App\Models\RestaurantTable::query()
            ->where('branch_id', $booking->branch_id)
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'status' => $r->status,
                'available' => $r->status === 'available' || $r->id === $booking->table_id,
            ]);

        return response()->json(['rooms' => $rooms, 'current_room_id' => $booking->table_id]);
    }

    /**
     * Reschedule: update res_date / res_time on a non-checked-in booking.
     * Mirrors Filament's rule: can't touch a checked-in or terminal booking.
     */
    public function reschedule(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();
        if ($booking->checked_in_at) {
            return response()->json(['ok' => false, 'error' => 'Cannot reschedule a checked-in booking.'], 422);
        }
        if (in_array($booking->status, [Booking::S_CANCELLED, Booking::S_COMPLETED, Booking::S_NO_SHOW], true)) {
            return response()->json(['ok' => false, 'error' => 'Booking is in a terminal state.'], 422);
        }

        $request->validate([
            'res_date' => 'required|date_format:Y-m-d',
            'res_time' => 'required|date_format:H:i',
        ]);

        // Recompute the slot window so the rescheduled booking stays consistent
        // with slot-blocking / no-show / overlap logic (these read res_start /
        // res_end, not res_date / res_time).
        [$resStart, $resEnd] = $this->deriveSlotWindow(
            $request->input('res_date'),
            $request->input('res_time'),
            (int) $booking->branch_id,
        );

        $booking->update([
            'res_date' => $request->input('res_date'),
            'res_time' => $request->input('res_time'),
            'res_start' => $resStart,
            'res_end' => $resEnd,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Derive the [res_start, res_end] datetime window from a date + time and
     * the branch's availability rule. Mirrors BookingService::calculateSlotEnd
     * and the Filament BookingResource so all three booking entry-points store
     * a consistent slot window.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function deriveSlotWindow($date, $time, int $branchId): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $dateStr = ($date instanceof \DateTimeInterface)
            ? $date->format('Y-m-d')
            : substr((string) $date, 0, 10);

        $timeStr = ($time instanceof \DateTimeInterface)
            ? $time->format('H:i:s')
            : (string) $time;

        if ($dateStr === '' || trim($timeStr) === '') {
            return [null, null];
        }

        if (preg_match('/^\d{2}:\d{2}$/', trim($timeStr))) {
            $timeStr = trim($timeStr).':00';
        }

        try {
            $start = Carbon::parse("{$dateStr} {$timeStr}", $tz)->seconds(0);
        } catch (\Throwable) {
            return [null, null];
        }

        $rule = \App\Models\BranchAvailabilityRule::query()
            ->where('branch_id', $branchId)
            ->where('day_of_week', $start->dayOfWeek)
            ->first();

        $minutes = (int) ($rule?->slot_length_minutes ?? $rule?->slot_step_minutes ?? config('booking.slot_interval', 30));
        $minutes = max(5, $minutes);

        return [$start, $start->copy()->addMinutes($minutes)->seconds(0)];
    }

    /**
     * Assign / change the room (table_id) on a booking.
     * If the booking is already checked-in, also flip the rooms' status flags
     * so reception's room board stays consistent.
     */
    public function assignRoom(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();
        $request->validate(['room_id' => 'nullable|integer|exists:restaurant_tables,id']);

        $newRoomId = $request->input('room_id');

        DB::transaction(function () use ($booking, $newRoomId) {
            $oldRoomId = $booking->table_id;

            if ($oldRoomId && $oldRoomId !== $newRoomId && $booking->checked_in_at) {
                \App\Models\RestaurantTable::where('id', $oldRoomId)->update(['status' => 'available']);
            }
            if ($newRoomId && $booking->checked_in_at) {
                \App\Models\RestaurantTable::where('id', $newRoomId)->update(['status' => 'occupied']);
            }

            $booking->update(['table_id' => $newRoomId]);

            // Mirror onto the Visit if one exists for this booking.
            if ($visit = Visit::query()->where('booking_id', $booking->id)->first()) {
                $visit->update(['restaurant_table_id' => $newRoomId]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Collect the consultation fee directly from the bookings sheet.
     * Same path as the check-in wizard's collect-fee: creates a
     * VisitPayment(kind=consultation, status=paid), auto-creating the
     * Visit shell when needed.
     */
    public function collectConsultation(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();

        // Manual desk methods only (cash/KNET/card/transfer); online + insurance
        // are excluded — see consultationMethods().
        $methods = $this->consultationMethods($booking);
        $allowedKeys = array_values(array_filter(array_column($methods, 'key')));

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.001',
            'method' => ['required', \Illuminate\Validation\Rule::in($allowedKeys)],
            'reference_no' => 'nullable|string|max:64',
        ]);

        // Card / KNET / transfer / online require a transaction/reference id —
        // cash doesn't. Enforce server-side per the method's config flag.
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

        DB::transaction(function () use ($booking, $data) {
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

        return response()->json(['ok' => true]);
    }

    /**
     * "Resend confirmation" — re-trigger the existing WhatsApp confirmation
     * template via the same service path Filament uses, so the customer
     * gets a fresh QR + caption.
     */
    public function resendConfirmation(Request $request, Booking $booking): JsonResponse
    {
        $this->abortIfNotReception();
        if (! $booking->msisdn) {
            return response()->json(['ok' => false, 'error' => 'Booking has no phone number to message.'], 422);
        }

        try {
            // Clear the idempotency lock so the QR + caption resend.
            $key = sprintf('wa:confirm:%d:%s', $booking->id, $booking->booking_code);
            cache()->forget($key);

            // Find the session for this msisdn (or create a minimal stub).
            $session = \App\Models\WhatsappSession::query()->firstOrCreate(
                ['phone' => $booking->msisdn],
                ['locale' => app()->getLocale() === 'ar' ? 'ar' : 'en']
            );

            $flow = app(\App\Services\BookingFlowService::class);
            $ref = new \ReflectionClass($flow);
            $m = $ref->getMethod('sendBookingConfirmation');
            $m->setAccessible(true);
            $m->invoke($flow, $booking, $session);
        } catch (\Throwable $e) {
            \Log::error('v2 resend confirmation failed', ['booking_id' => $booking->id, 'err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'Could not resend. Check logs.'], 500);
        }

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    protected function normalizeFilters(Request $request): array
    {
        $when = (string) $request->input('when', 'today');
        $allowedWhen = ['today', 'tomorrow', 'week', 'month', 'past', 'any'];
        if (! in_array($when, $allowedWhen, true)) $when = 'today';

        return [
            'q' => trim((string) $request->input('q', '')),
            'when' => $when,
            'status' => (array) $request->input('status', []),
            'branch_id' => $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            'doctor_id' => $request->filled('doctor_id') ? (int) $request->input('doctor_id') : null,
            'checked_in' => $request->input('checked_in'), // 'yes' | 'no' | null
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => 20,
        ];
    }

    protected function baseQuery(array $f)
    {
        $q = Booking::query()->with(['patient', 'doctor', 'branch']);

        if (!empty($f['status'])) {
            $q->whereIn('status', $f['status']);
        }
        if ($f['branch_id']) {
            $q->where('branch_id', $f['branch_id']);
        }
        if ($f['doctor_id']) {
            $q->where('doctor_id', $f['doctor_id']);
        }
        if ($f['checked_in'] === 'yes') {
            $q->whereNotNull('checked_in_at');
        } elseif ($f['checked_in'] === 'no') {
            $q->whereNull('checked_in_at');
        }
        if ($f['q'] !== '' && mb_strlen($f['q']) >= 2) {
            $like = '%'.$f['q'].'%';
            $q->where(function ($w) use ($like) {
                $w->where('booking_code', 'like', $like)
                    ->orWhere('msisdn', 'like', $like)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $like));
            });
        }

        switch ($f['when']) {
            case 'today':
                $q->whereDate('res_date', today());
                break;
            case 'tomorrow':
                $q->whereDate('res_date', today()->addDay());
                break;
            case 'week':
                $q->whereBetween('res_date', [today()->startOfWeek(), today()->endOfWeek()]);
                break;
            case 'month':
                $q->whereBetween('res_date', [today()->startOfMonth(), today()->endOfMonth()]);
                break;
            case 'past':
                $q->whereDate('res_date', '<', today());
                break;
            case 'any':
                // no date filter
                break;
        }

        return $q;
    }

    protected function queryBookings(array $f): array
    {
        $p = $this->baseQuery($f)
            ->orderByRaw('res_date desc, res_time desc')
            ->paginate($f['per_page'], ['*'], 'page', $f['page'])
            ->withQueryString();

        return [
            'data' => collect($p->items())->map(fn (Booking $b) => $this->transformBooking($b))->values(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'from' => $p->firstItem(),
                'to' => $p->lastItem(),
            ],
        ];
    }

    protected function statusCounts(array $f): array
    {
        // Counts for the current filter scope, broken by status.
        $base = $this->baseQuery(array_merge($f, ['status' => []])); // strip status from counts

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', Booking::S_PENDING)->count(),
            'confirmed' => (clone $base)->where('status', Booking::S_CONFIRMED)->count(),
            'completed' => (clone $base)->where('status', Booking::S_COMPLETED)->count(),
            'cancelled' => (clone $base)->where('status', Booking::S_CANCELLED)->count(),
            'no_show' => (clone $base)->where('status', Booking::S_NO_SHOW)->count(),
            'checked_in' => (clone $base)->whereNotNull('checked_in_at')->count(),
        ];
    }

    protected function transformBooking(Booking $b, array $extra = []): array
    {
        return array_merge([
            'id' => $b->id,
            'booking_code' => $b->booking_code,
            'status' => $b->status,
            'res_date' => optional($b->res_date)->toDateString(),
            'res_time' => $b->res_time,
            'party_size' => $b->party_size,
            'msisdn' => $b->msisdn,
            'source' => $b->source,
            'checked_in_at' => optional($b->checked_in_at)->toIso8601String(),
            'cancelled_at' => optional($b->cancelled_at)->toIso8601String(),
            'no_show_at' => optional($b->no_show_at)->toIso8601String(),
            'created_at' => optional($b->created_at)->toIso8601String(),
            'patient' => $b->patient ? [
                'id' => $b->patient->id,
                'name' => $b->patient->name,
                'msisdn' => $b->patient->phone ?? null,
            ] : null,
            'doctor' => $b->doctor ? [
                'id' => $b->doctor->id,
                'name' => $b->doctor->name,
            ] : null,
            'branch' => $b->branch ? [
                'id' => $b->branch->id,
                'name' => $b->branch->getTranslation('name', app()->getLocale(), true),
            ] : null,
        ], $extra);
    }
}
