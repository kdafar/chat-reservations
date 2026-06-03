<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Doctor Schedule — v2 replacement for the Filament DoctorSchedule page.
 *
 * A per-doctor appointment board: pick a doctor + period + time-of-day and see
 * that doctor's day, grouped by date, with check-in status and a one-tap
 * WhatsApp link. Check-in itself is handled by the dedicated Check-in wizard
 * (we deep-link rows there) rather than duplicating that transaction here.
 *
 * Doctors who log in see only their own schedule; the doctor picker is hidden.
 */
class DoctorScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        // The board exposes patient contact details, so it is limited to
        // management roles and to doctors (who are then locked to their own
        // schedule below). Other staff are not permitted to browse arbitrary
        // doctors' appointment lists.
        $isDoctor = $user && Doctor::where('user_id', $user->id)->exists();
        abort_unless(
            $user && ($user->hasRole(['admin', 'super_admin', 'clinic_admin']) || $isDoctor),
            403,
            'Not authorized to view doctor schedules.'
        );

        $tz = config('app.timezone', 'Asia/Kuwait');

        // A logged-in doctor is locked to their own schedule.
        $ownDoctorId = $user->hasRole(['admin', 'super_admin'])
            ? null
            : Doctor::where('user_id', $user->id)->value('id');

        $doctors = Doctor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all();

        $filters = [
            'doctor_id' => $ownDoctorId ?: ($request->input('doctor_id') ? (int) $request->input('doctor_id') : ($doctors[0]['id'] ?? null)),
            'period' => $request->input('period', 'today'),
            'slot' => $request->input('slot', 'all'),
        ];

        $query = Booking::query()
            ->with(['patient:id,name,phone', 'doctor:id,name'])
            ->whereIn('status', ['confirmed', 'pending']);

        if ($filters['doctor_id']) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        $this->applyPeriod($query, $filters['period'], $tz);
        $this->applySlot($query, $filters['slot']);

        $bookings = $query->orderBy('res_date')->orderBy('res_time')->limit(500)->get();

        // Group rows by date for a timeline view.
        $groups = $bookings->groupBy(fn (Booking $b) => optional($b->res_date)->toDateString() ?? 'unknown')
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'date_label' => $this->dateLabel($date, $tz),
                'items' => $rows->map(fn (Booking $b) => $this->present($b))->values()->all(),
            ])->values()->all();

        return Inertia::render('DoctorSchedule/Index', [
            'filters' => $filters,
            'doctors' => $doctors,
            'lockedDoctor' => $ownDoctorId ? true : false,
            'groups' => $groups,
            'stats' => [
                'total' => $bookings->count(),
                'checked_in' => $bookings->whereNotNull('checked_in_at')->count(),
                'pending' => $bookings->whereNull('checked_in_at')->count(),
            ],
        ]);
    }

    protected function applyPeriod(Builder $q, ?string $period, string $tz): void
    {
        $now = Carbon::now($tz);
        match ($period) {
            'tomorrow' => $q->whereDate('res_date', $now->copy()->addDay()->toDateString()),
            'week' => $q->whereBetween('res_date', [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()]),
            'all' => $q->whereDate('res_date', '>=', $now->toDateString()),
            default => $q->whereDate('res_date', $now->toDateString()), // today
        };
    }

    protected function applySlot(Builder $q, ?string $slot): void
    {
        match ($slot) {
            'morning' => $q->whereTime('res_time', '>=', '08:00:00')->whereTime('res_time', '<', '12:00:00'),
            'afternoon' => $q->whereTime('res_time', '>=', '12:00:00')->whereTime('res_time', '<', '17:00:00'),
            'evening' => $q->whereTime('res_time', '>=', '17:00:00')->whereTime('res_time', '<', '23:00:00'),
            default => null,
        };
    }

    protected function dateLabel(string $date, string $tz): string
    {
        if ($date === 'unknown') {
            return '—';
        }
        $d = Carbon::parse($date, $tz);
        $today = Carbon::now($tz)->startOfDay();
        if ($d->isSameDay($today)) {
            return 'Today · '.$d->format('D, M j');
        }
        if ($d->isSameDay($today->copy()->addDay())) {
            return 'Tomorrow · '.$d->format('D, M j');
        }

        return $d->format('l, M j');
    }

    protected function present(Booking $b): array
    {
        $phone = preg_replace('/\D+/', '', (string) ($b->msisdn ?: $b->patient?->phone ?? ''));

        return [
            'id' => $b->id,
            'time' => $b->res_time ? Carbon::parse($b->res_time)->format('h:i A') : null,
            'patient' => $b->patient?->name ?: $b->name ?: '—',
            'phone' => $b->msisdn ?: $b->patient?->phone,
            'wa' => $phone !== '' ? 'https://wa.me/'.$phone : null,
            'doctor' => $b->doctor?->name,
            'booking_code' => $b->booking_code,
            'status' => $b->status,
            'checked_in' => ! is_null($b->checked_in_at),
        ];
    }
}
