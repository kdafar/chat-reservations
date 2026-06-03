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
        // Reception + admin also see awaiting_payment visits — they need to
        // collect remaining payments and then click Complete visit. Doctors
        // don't see these (their work is done; the visit left their queue).
        $statuses = ['awaiting_doctor', 'in_progress', 'awaiting_stock'];
        $showsBilling = $this->isAdminUser() || $this->isReceptionUser();
        if ($showsBilling) {
            $statuses[] = 'awaiting_payment';
        }

        $visits = $this->queueQuery($statuses)
            ->orderByRaw("FIELD(status, 'awaiting_payment', 'awaiting_doctor', 'in_progress', 'awaiting_stock')")
            ->orderBy('queued_at')
            ->get()
            ->map(fn (Visit $v) => $this->transform($v));

        // Pending check-ins — only super_admin / admin / clinic_reception
        // see these. Doctors never do (their queue starts at awaiting_doctor).
        $pendingCheckins = $this->canSeePendingCheckins()
            ? $this->pendingCheckinQuery()->get()->map(fn (Booking $b) => $this->transformBooking($b))
            : collect();

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
            ->with(['patient', 'doctor', 'branch'])
            ->whereNull('checked_in_at')
            ->whereIn('status', [Booking::S_PENDING, Booking::S_CONFIRMED])
            ->whereDate('res_date', today())
            ->orderBy('res_time');
    }

    protected function transformBooking(Booking $b): array
    {
        $age = null;
        if ($b->patient && $b->patient->dob) {
            try { $age = Carbon::parse($b->patient->dob)->age; } catch (\Throwable) {}
        }

        $fee = (float) ($b->doctor->consultation_fee ?? 0);
        $paid = $fee > 0 ? (float) VisitPayment::query()
            ->whereIn('visit_id', Visit::query()->where('booking_id', $b->id)->pluck('id'))
            ->where('kind', VisitPayment::KIND_CONSULTATION)
            ->where('status', 'paid')
            ->sum('amount') : 0.0;

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
            'notes' => $b->notes,
            'res_time' => $b->res_time,
            'res_date' => optional($b->res_date)->toDateString(),
            'fee' => [
                'amount' => $fee,
                'paid' => $fee > 0 && $paid >= $fee,
                'paid_amount' => $paid,
            ],
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

    protected function transform(Visit $v): array
    {
        $age = null;
        if ($v->patient && $v->patient->dob) {
            try { $age = Carbon::parse($v->patient->dob)->age; } catch (\Throwable) {}
        }

        $consultationFee = (float) ($v->doctor->consultation_fee ?? 0);
        $paidTotal = (float) VisitPayment::query()
            ->where('visit_id', $v->id)
            ->where('kind', VisitPayment::KIND_CONSULTATION)
            ->where('status', 'paid')
            ->sum('amount');

        return [
            'id' => $v->id,
            'status' => $v->status,
            'queued_at' => optional($v->queued_at)->toIso8601String(),
            'checked_in_at' => optional($v->checked_in_at)->toIso8601String(),
            'service_started_at' => optional($v->service_started_at)->toIso8601String(),
            'booking_code' => $v->booking_code,
            'notes' => $v->notes,
            'fee' => [
                'amount' => $consultationFee,
                'paid' => $paidTotal >= $consultationFee && $consultationFee > 0,
                'paid_amount' => $paidTotal,
            ],
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
