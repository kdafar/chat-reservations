<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Support\VisitAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WaitingPatientsController extends Controller
{
    use VisitAuthorization;

    /**
     * Mirror of the read-side of App\Filament\Pages\WaitingPatients. Same
     * data, same scoping rules — different (better) UI. The old Filament
     * page stays operational at /admin/waiting-patients.
     */
    public function index(Request $request): Response
    {
        // The live patient queue is a clinical / front-desk surface: admin,
        // reception, doctors and nurses. Finance-only roles (e.g. accountant)
        // have no business in the queue, so they're blocked here — not just
        // hidden in the sidebar. Matches the 'waiting' navGate in AppLayout.
        $u = $request->user();
        $maySeeQueue = $this->isAdminUser()
            || $this->isReceptionUser()
            || $this->isDoctorUser()
            || (bool) ($u && method_exists($u, 'hasRole') && $u->hasRole('clinic_nurse'));
        if (! $maySeeQueue) {
            abort(403, 'Not authorized to view the patient queue.');
        }

        // Reception + admin also see awaiting_payment visits — they need to
        // collect remaining payments and then click Complete visit. Doctors
        // don't see these (their work is done; the visit left their queue).
        $statuses = ['awaiting_doctor', 'in_progress', 'awaiting_stock'];
        $showsBilling = $this->isAdminUser() || $this->isReceptionUser();
        if ($showsBilling) {
            $statuses[] = 'awaiting_payment';
        }

        $visitRows = $this->queueQuery($statuses)
            ->orderByRaw("FIELD(status, 'awaiting_payment', 'awaiting_doctor', 'in_progress', 'awaiting_stock')")
            ->orderBy('queued_at')
            ->get();

        // Pending check-ins — only super_admin / admin / clinic_reception
        // see these. Doctors never do (their queue starts at awaiting_doctor).
        $bookingRows = $this->canSeePendingCheckins()
            ? $this->pendingCheckinQuery()->get()
            : collect();

        // Precompute consultation-paid totals in bulk (was an N+1: one sum
        // query per card, which auto-refresh hammered every 12s).
        $paidByVisit = $this->consultationPaidByVisit($visitRows->pluck('id')->all());
        $paidByBooking = $this->consultationPaidByBooking($bookingRows->pluck('id')->all());

        // All-kind paid totals (consultation + items + services + other) so the
        // card can show the full amount paid and the outstanding balance — also
        // bulk-grouped to stay N+1-free under the 12s auto-refresh.
        $allPaidByVisit = $this->allPaidByVisit($visitRows->pluck('id')->all());
        $allPaidByBooking = $this->allPaidByBooking($bookingRows->pluck('id')->all());

        // Active primary policy per patient on screen, resolved in ONE query
        // across every visible patient id (visits + pending check-ins).
        $patientIds = $visitRows->pluck('patient_id')
            ->concat($bookingRows->pluck('patient_id'))
            ->filter()->unique()->values()->all();
        $policyByPatient = $this->primaryPolicyByPatient($patientIds);

        // Lab state per visit, in one query — so a doctor can see "results ready"
        // (or "still at the lab") on the queue card without opening the visit.
        $labByVisit = $this->labStateByVisit($visitRows->pluck('id')->all());

        // The offer the patient picked on the website, carried over from the
        // booking onto the visit card — two bulk queries for the whole screen.
        $requestedPackageByVisit = $this->requestedPackageByVisit($visitRows);

        $visits = $visitRows->map(fn (Visit $v) => $this->transform(
            $v,
            (float) ($paidByVisit[$v->id] ?? 0),
            (float) ($allPaidByVisit[$v->id] ?? 0),
            $policyByPatient[$v->patient_id] ?? null,
            $labByVisit[$v->id] ?? null,
            $requestedPackageByVisit[$v->id] ?? null,
        ));
        $pendingCheckins = $bookingRows->map(fn (Booking $b) => $this->transformBooking(
            $b,
            (float) ($paidByBooking[$b->id] ?? 0),
            (float) ($allPaidByBooking[$b->id] ?? 0),
            $policyByPatient[$b->patient_id] ?? null,
        ));

        $combined = $pendingCheckins->concat($visits)->values();

        // Doctor-only: today's scheduled bookings (whatever the status) so
        // the doctor can see who's coming, including patients who haven't
        // been checked in yet. Read-only — they can't action these.
        $doctorSchedule = collect();
        if ($this->isDoctorUser() && ! $this->isAdminUser() && ! $this->isReceptionUser()) {
            $doctorId = $this->doctorIdForCurrentUser();
            $doctorSchedule = Booking::query()
                ->with(['patient', 'branch'])
                ->where('doctor_id', $doctorId)
                ->whereDate('res_date', today())
                ->whereIn('status', [Booking::S_PENDING, Booking::S_CONFIRMED])
                ->orderBy('res_time')
                ->get()
                ->map(fn (Booking $b) => [
                    'id' => $b->id,
                    'booking_code' => $b->booking_code,
                    'res_time' => $b->res_time,
                    'checked_in' => ! is_null($b->checked_in_at),
                    'patient_name' => $b->patient?->name ?? '—',
                ]);
        }

        return Inertia::render('WaitingPatients/Index', [
            'visits' => $combined,
            'counts' => [
                'pending_checkin' => $pendingCheckins->count(),
                'awaiting_doctor' => $visits->where('status', 'awaiting_doctor')->count(),
                'in_progress' => $visits->where('status', 'in_progress')->count(),
                'awaiting_stock' => $visits->where('status', 'awaiting_stock')->count(),
                // Total awaiting payment (for the pill under the header).
                'awaiting_payment' => $this->awaitingPaymentScope()->count(),
                // Subset that's actually on the queue (visible to reception
                // /admin via the new awaiting_payment chip). For doctors
                // this is always 0 since they don't see billing cards.
                'awaiting_payment_visible' => $visits->where('status', 'awaiting_payment')->count(),
            ],
            'doctor_schedule' => $doctorSchedule,
            'is_admin' => $this->isAdminUser(),
            'is_reception' => $this->isReceptionUser() && ! $this->isAdminUser(),
            'is_doctor' => $this->isDoctorUser(),
            'doctor_id' => $this->doctorIdForCurrentUser(),
            // Doctor list for reception/admin to reassign a visit's doctor from
            // the queue. Branch-scoped via the Doctor model's global scope.
            'doctor_options' => $showsBilling
                ? \App\Models\Doctor::query()->where('is_active', true)->orderBy('name')
                    ->get(['id', 'name', 'branch_id'])
                    ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'branch_id' => $d->branch_id])
                    ->all()
                : [],
            // Clock-in/out nudge — Waiting Patients is where clinical/front-desk
            // staff land, so this reaches the people who actually clock in.
            'attendance' => $this->currentUserAttendance(),
        ]);
    }

    /**
     * The current user's attendance state for today, driving the clock-in/out
     * widget. Null for users who don't track attendance (no permission).
     */
    protected function currentUserAttendance(): ?array
    {
        $user = auth()->user();
        if (! $user || ! $user->can('view_any_staff_attendances')) {
            return null;
        }

        $today = Carbon::today(config('app.timezone', 'Asia/Kuwait'))->toDateString();
        $row = \App\Models\StaffAttendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        if (! $row) {
            return ['status' => 'none'];
        }
        if (! $row->clock_out_at) {
            return [
                'status' => 'on_shift',
                'id' => $row->id,
                'clock_in_at' => optional($row->clock_in_at)->toIso8601String(),
            ];
        }

        return [
            'status' => 'done',
            'id' => $row->id,
            'clock_in_at' => optional($row->clock_in_at)->toIso8601String(),
            'clock_out_at' => optional($row->clock_out_at)->toIso8601String(),
            'hours' => (float) $row->hours_worked,
        ];
    }

    /**
     * Today's confirmed/pending bookings that haven't been checked in yet.
     * Surfaces "Patient is waiting at reception" entries on the queue for
     * the front desk to action. Branch-scoped via BelongsToBranchScope.
     */
    protected function pendingCheckinQuery(): Builder
    {
        return Booking::query()
            ->with(['patient', 'doctor', 'branch', 'requestedPackage'])
            ->whereNull('checked_in_at')
            ->whereIn('status', [Booking::S_PENDING, Booking::S_CONFIRMED])
            ->whereDate('res_date', today())
            ->orderBy('res_time');
    }

    protected function transformBooking(Booking $b, float $paid = 0.0, float $paidTotal = 0.0, ?array $policy = null): array
    {
        $age = null;
        if ($b->patient && $b->patient->dob) {
            try { $age = Carbon::parse($b->patient->dob)->age; } catch (\Throwable) {}
        }

        $fee = (float) ($b->doctor->consultation_fee ?? 0);

        return [
            // Booking rows are mixed into the visits array but use a
            // distinct pseudo-status so the frontend can render them
            // differently and route the card click to CheckinModal.
            'id' => 'b'.$b->id,        // string prefix avoids collisions with visit ids
            'booking_id' => $b->id,
            'is_booking' => true,
            'status' => 'pending_checkin',
            'queued_at' => null,
            'checked_in_at' => null,
            'service_started_at' => null,
            'booking_code' => $b->booking_code,
            'source' => $b->source,
            'notes' => $b->notes,
            'res_time' => $b->res_time,
            'res_date' => optional($b->res_date)->toDateString(),
            'fee' => [
                'amount' => $fee,
                'paid' => $fee > 0 && $paid >= $fee,
                'paid_amount' => $paid,
                // All-kind paid sum across the booking's visits (0 until checked
                // in) and the outstanding consultation balance for the card.
                'paid_total' => round($paidTotal, 3),
                'balance' => round(max(0, $fee - $paidTotal), 3),
            ],
            // No visit yet → no line discounts and no labs; both surfaced for
            // payload parity with the visit cards.
            'discount_total' => 0.0,
            'policy' => $policy,
            'lab' => null,
            // The package/offer the patient picked on the website when they
            // booked. Eager-loaded in pendingCheckinQuery() — never lazily.
            'requested_package' => $this->formatRequestedPackage($b->requestedPackage, $b->branch_id),
            'patient' => $b->patient ? [
                'id' => $b->patient->id,
                'name' => $b->patient->name,
                'msisdn' => $b->patient->phone ?? $b->msisdn ?? null,
                'age' => $age,
                'gender' => $b->patient->gender ?? null,
            ] : null,
            'doctor' => $b->doctor ? [
                'id' => $b->doctor->id,
                'name' => $b->doctor->name,
            ] : null,
            'branch' => $b->branch ? [
                'id' => $b->branch->id,
                'name' => $b->branch->getTranslation('name', app()->getLocale(), true),
            ] : null,
            'room' => null,
        ];
    }

    /**
     * Shape the "patient asked for this offer" payload for a card. Null when
     * there's nothing to show, so every row carries the key either way.
     *
     * `branch_mismatch` matters: offers are branch-scoped, so a patient can
     * pick an offer published by one branch and then book at another. Reception
     * should be warned rather than silently sell the wrong thing — but only
     * when the package is actually pinned to a branch (a partner-wide package
     * with a null branch_id is valid everywhere).
     */
    protected function formatRequestedPackage(?\App\Models\ClinicPackage $p, ?int $contextBranchId): ?array
    {
        if (! $p) {
            return null;
        }

        return [
            'id' => $p->id,
            'name' => $p->localized_name,
            'price' => round((float) $p->effective_price, 3),
            'has_discount' => (bool) $p->has_discount,
            'branch_mismatch' => $p->branch_id !== null
                && $contextBranchId !== null
                && (int) $p->branch_id !== (int) $contextBranchId,
        ];
    }

    /**
     * Requested package per VISIT id, resolved in bulk (TWO queries total, no
     * matter how many cards are on screen — this page auto-refreshes every 12s).
     *
     * The request lives on the booking, so a checked-in patient would otherwise
     * lose it the moment reception converts the booking into a visit. It stops
     * being useful the moment the package is actually on the visit, so rows
     * where the visit already carries that package are dropped (returned as
     * absent → the caller renders null).
     *
     * Returns [visit_id => array|null] in the formatRequestedPackage() shape.
     */
    protected function requestedPackageByVisit(\Illuminate\Support\Collection $visits): array
    {
        $bookingIds = $visits->pluck('booking_id')->filter()->unique()->values()->all();
        if (empty($bookingIds)) {
            return [];
        }

        // Query 1: the bookings that carry a request, with their package.
        $bookings = Booking::query()
            ->with('requestedPackage')
            ->whereIn('id', $bookingIds)
            ->whereNotNull('requested_package_id')
            ->get(['id', 'branch_id', 'requested_package_id'])
            ->keyBy('id');

        if ($bookings->isEmpty()) {
            return [];
        }

        // Query 2: packages already attached to the visits on screen, so an
        // honoured request stops shouting once it's been actioned.
        $attached = \App\Models\VisitPackage::query()
            ->whereIn('visit_id', $visits->pluck('id')->all())
            ->get(['visit_id', 'clinic_package_id'])
            ->groupBy('visit_id')
            ->map(fn ($rows) => $rows->pluck('clinic_package_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $out = [];
        foreach ($visits as $v) {
            $booking = $v->booking_id ? ($bookings[$v->booking_id] ?? null) : null;
            if (! $booking || ! $booking->requestedPackage) {
                continue;
            }
            if (in_array((int) $booking->requested_package_id, $attached[$v->id] ?? [], true)) {
                continue; // already on the visit — noise from here on.
            }
            $out[$v->id] = $this->formatRequestedPackage($booking->requestedPackage, $v->branch_id);
        }

        return $out;
    }

    /**
     * Lab state per visit id, in ONE query across every card on screen.
     * Returns [visit_id => ['pending' => n, 'ready' => n, 'urgent' => bool,
     * 'worst_flag' => string|null]] — 'ready' means a released report the doctor
     * hasn't signed off yet, which is the thing worth interrupting them for.
     */
    protected function labStateByVisit(array $visitIds): array
    {
        if (empty($visitIds)) {
            return [];
        }

        $orders = \App\Models\Lab\LabOrder::query()
            ->whereIn('visit_id', $visitIds)
            ->with('items:id,lab_order_id,flag')
            ->get(['id', 'visit_id', 'status', 'priority', 'reviewed_at']);

        $out = [];
        foreach ($orders as $o) {
            $row = $out[$o->visit_id] ?? ['pending' => 0, 'ready' => 0, 'urgent' => false, 'worst_flag' => null];

            if ($o->isOpen()) {
                $row['pending']++;
                $row['urgent'] = $row['urgent'] || $o->isUrgent();
            } elseif ($o->status === \App\Models\Lab\LabOrder::STATUS_COMPLETED) {
                if ($o->reviewed_at === null) {
                    $row['ready']++;
                }
                $worst = $o->worstFlag();
                $rank = ['critical' => 4, 'high' => 3, 'low' => 2, 'normal' => 1];
                if ($worst && ($rank[$worst] ?? 0) > ($rank[$row['worst_flag']] ?? 0)) {
                    $row['worst_flag'] = $worst;
                }
            }

            $out[$o->visit_id] = $row;
        }

        return $out;
    }

    protected function transform(Visit $v, float $paidConsultation = 0.0, float $paidTotal = 0.0, ?array $policy = null, ?array $lab = null, ?array $requestedPackage = null): array
    {
        $age = null;
        if ($v->patient && $v->patient->dob) {
            try { $age = Carbon::parse($v->patient->dob)->age; } catch (\Throwable) {}
        }

        $consultationFee = (float) ($v->doctor->consultation_fee ?? 0);

        // Outstanding balance from the visit's own snapshot columns minus the
        // bulk all-kind paid total — avoids calling VisitCostingService per row.
        $discountTotal = (float) ($v->discount_total ?? 0);
        $billed = (float) ($v->fees_total ?? 0)
            + (float) ($v->packages_price_total ?? 0)
            + (float) ($v->items_price_total ?? 0)
            - $discountTotal;
        $balance = max(0, $billed - $paidTotal);

        return [
            'id' => $v->id,
            'status' => $v->status,
            'queued_at' => optional($v->queued_at)->toIso8601String(),
            'checked_in_at' => optional($v->checked_in_at)->toIso8601String(),
            'service_started_at' => optional($v->service_started_at)->toIso8601String(),
            'booking_code' => $v->booking_code,
            'source' => $v->source,
            'notes' => $v->notes,
            'fee' => [
                'amount' => $consultationFee,
                'paid' => $paidConsultation >= $consultationFee && $consultationFee > 0,
                'paid_amount' => $paidConsultation,
                // All-kind paid sum + outstanding balance across the whole bill.
                'paid_total' => round($paidTotal, 3),
                'balance' => round($balance, 3),
            ],
            'discount_total' => round($discountTotal, 3),
            'policy' => $policy,
            'lab' => $lab,
            // Resolved in bulk by requestedPackageByVisit() — null once the
            // package is on the visit, or when the booking never asked for one.
            'requested_package' => $requestedPackage,
            'patient' => $v->patient ? [
                'id' => $v->patient->id,
                'name' => $v->patient->name,
                'msisdn' => $v->patient->phone ?? $v->patient->msisdn ?? null,
                'age' => $age,
                'gender' => $v->patient->gender ?? null,
            ] : null,
            'doctor' => $v->doctor ? [
                'id' => $v->doctor->id,
                'name' => $v->doctor->name,
            ] : null,
            'branch' => $v->branch ? [
                'id' => $v->branch->id,
                'name' => $v->branch->getTranslation('name', app()->getLocale(), true),
            ] : null,
            'room' => $v->room ? [
                'id' => $v->room->id,
                'name' => $v->room->name,
            ] : null,
        ];
    }

    /**
     * Sum of paid consultation payments per visit id, in one grouped query.
     * Returns [visit_id => paid_amount]. Avoids an N+1 across queue cards.
     */
    protected function consultationPaidByVisit(array $visitIds): \Illuminate\Support\Collection
    {
        if (empty($visitIds)) {
            return collect();
        }

        return VisitPayment::query()
            ->whereIn('visit_id', $visitIds)
            ->where('kind', VisitPayment::KIND_CONSULTATION)
            ->where('status', 'paid')
            ->groupBy('visit_id')
            ->selectRaw('visit_id, SUM(amount) as paid')
            ->pluck('paid', 'visit_id');
    }

    /**
     * Sum of paid consultation payments per booking id (across the booking's
     * visits), in one grouped query. Returns [booking_id => paid_amount].
     */
    protected function consultationPaidByBooking(array $bookingIds): \Illuminate\Support\Collection
    {
        if (empty($bookingIds)) {
            return collect();
        }

        return VisitPayment::query()
            ->join('visits', 'visits.id', '=', 'visit_payments.visit_id')
            ->whereIn('visits.booking_id', $bookingIds)
            ->where('visit_payments.kind', VisitPayment::KIND_CONSULTATION)
            ->where('visit_payments.status', 'paid')
            ->groupBy('visits.booking_id')
            ->selectRaw('visits.booking_id as booking_id, SUM(visit_payments.amount) as paid')
            ->pluck('paid', 'booking_id');
    }

    /**
     * Sum of ALL paid payments (every kind) per visit id, in one grouped query.
     * Returns [visit_id => paid_amount]. Drives the card's total-paid + balance.
     */
    protected function allPaidByVisit(array $visitIds): \Illuminate\Support\Collection
    {
        if (empty($visitIds)) {
            return collect();
        }

        return VisitPayment::query()
            ->whereIn('visit_id', $visitIds)
            ->where('status', 'paid')
            ->groupBy('visit_id')
            ->selectRaw('visit_id, SUM(amount) as paid')
            ->pluck('paid', 'visit_id');
    }

    /**
     * Sum of ALL paid payments (every kind) per booking id, across the booking's
     * visits, in one grouped query. Returns [booking_id => paid_amount].
     */
    protected function allPaidByBooking(array $bookingIds): \Illuminate\Support\Collection
    {
        if (empty($bookingIds)) {
            return collect();
        }

        return VisitPayment::query()
            ->join('visits', 'visits.id', '=', 'visit_payments.visit_id')
            ->whereIn('visits.booking_id', $bookingIds)
            ->where('visit_payments.status', 'paid')
            ->groupBy('visits.booking_id')
            ->selectRaw('visits.booking_id as booking_id, SUM(visit_payments.amount) as paid')
            ->pluck('paid', 'booking_id');
    }

    /**
     * Active primary insurance policy per patient id, resolved in ONE query for
     * all patients on screen (no per-row InsuranceService call). Returns
     * [patient_id => ['insurer' => string|null, 'plan' => string|null, 'number' => string|null]].
     *
     * Picks the highest-priority active policy per patient: primary first, then
     * latest effective_from. Insurer/plan names are locale-aware (name_ar / name).
     */
    protected function primaryPolicyByPatient(array $patientIds): array
    {
        if (empty($patientIds)) {
            return [];
        }

        $ar = app()->getLocale() === 'ar';

        $policies = \App\Models\Insurance\PatientInsurancePolicy::query()
            ->with(['insurer:id,name,name_ar', 'plan:id,name,name_ar'])
            ->whereIn('patient_id', $patientIds)
            ->active()
            ->orderByDesc('is_primary')
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($policies as $p) {
            // First row per patient wins (ordering above = primary → newest).
            if (isset($out[$p->patient_id])) {
                continue;
            }
            $insurer = $p->insurer;
            $plan = $p->plan;
            $out[$p->patient_id] = [
                'insurer' => $insurer ? (($ar ? $insurer->name_ar : null) ?: $insurer->name) : null,
                'plan' => $plan ? (($ar ? $plan->name_ar : null) ?: $plan->name) : null,
                'number' => $p->policy_number,
            ];
        }

        return $out;
    }

    protected function awaitingPaymentScope(): Builder
    {
        return $this->applyVisibilityScope(
            Visit::query()->where('status', Visit::STATUS_AWAITING_PAYMENT)
        );
    }

    protected function queueQuery(array $statuses = ['awaiting_doctor', 'in_progress', 'awaiting_stock']): Builder
    {
        return $this->applyVisibilityScope(
            Visit::query()
                ->with(['patient', 'doctor', 'branch', 'room'])
                ->whereIn('status', $statuses)
                ->whereNull('completed_at')
        );
    }

    /**
     * Apply visibility scope based on the current user's role.
     *   - admin / super_admin / clinic_admin: full queue
     *   - clinic_reception: full queue (they coordinate front desk)
     *   - doctor: only visits assigned to them
     *   - anyone else: nothing
     *
     * BelongsToBranchScope on Visit already narrows to accessible branches.
     */
    protected function applyVisibilityScope(Builder $q): Builder
    {
        if ($this->isAdminUser() || $this->isReceptionUser()) {
            return $q;
        }

        $doctorId = $this->doctorIdForCurrentUser();
        if ($doctorId === null) {
            return $q->whereRaw('1=0');
        }

        // Doctor-only safety net: never surface a visit that hasn't been
        // physically checked in by reception. The status alone shouldn't be
        // trusted as data anomalies (manual visit rows, half-rolled-back
        // check-ins) can leave awaiting_doctor + null checked_in_at.
        return $q->where('doctor_id', $doctorId)
            ->whereNotNull('checked_in_at');
    }
}
