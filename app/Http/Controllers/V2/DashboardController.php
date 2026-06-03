<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Visit;
use App\Models\VisitPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * v2 Daily Dashboard
 *
 * Read-only daily snapshot for clinic admins/doctors. All queries go through
 * BelongsToBranchScope on the Visit/Booking models. VisitPayment doesn't
 * carry branch_id directly, so revenue queries scope through a Visit
 * subselect — Eloquent applies the global scope inside whereHas/whereIn(sub).
 */
class DashboardController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        // The dashboard surfaces clinic-wide revenue + utilization, so it is
        // restricted to management/reporting roles. Other staff who land here
        // (e.g. via the "switch to v2" button) are sent to the live queue
        // instead of hitting a 403 on their entry point.
        if (! auth()->user()?->can('view_clinic_reports')) {
            return redirect()->route('v2.waiting-patients');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $today = Carbon::today($tz);
        $yesterday = (clone $today)->subDay();
        $startOfWindow = (clone $today)->subDays(29); // 30 days inclusive of today

        // -----------------------------------------------------------------
        // KPIs
        // -----------------------------------------------------------------
        $todayRevenue = $this->scopedPaymentsForDay($today);
        $yesterdayRevenue = $this->scopedPaymentsForDay($yesterday);

        $todayVisits = Visit::query()
            ->whereDate('checked_in_at', $today->toDateString())
            ->count();
        $yesterdayVisits = Visit::query()
            ->whereDate('checked_in_at', $yesterday->toDateString())
            ->count();

        $todayNoShows = Booking::query()
            ->where('status', Booking::S_NO_SHOW)
            ->whereDate('res_date', $today->toDateString())
            ->count();

        // Average wait minutes: queued_at -> service_started_at, for visits
        // whose service started today.
        $avgWaitRaw = Visit::query()
            ->whereDate('service_started_at', $today->toDateString())
            ->whereNotNull('queued_at')
            ->whereNotNull('service_started_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, queued_at, service_started_at)) as avg_wait')
            ->value('avg_wait');
        $avgWait = $avgWaitRaw !== null ? round((float) $avgWaitRaw, 1) : 0.0;

        $kpis = [
            'today_revenue' => round($todayRevenue, 3),
            'today_visits' => (int) $todayVisits,
            'today_no_shows' => (int) $todayNoShows,
            'today_avg_wait_min' => $avgWait,
            'deltas' => [
                'revenue_pct' => $this->pctDelta($todayRevenue, $yesterdayRevenue),
                'visits_pct' => $this->pctDelta((float) $todayVisits, (float) $yesterdayVisits),
            ],
        ];

        // -----------------------------------------------------------------
        // Revenue trend (last 30 days, zero-filled)
        // -----------------------------------------------------------------
        // Scope payments to visits the user can see by joining via a Visit
        // subselect — this applies BelongsToBranchScope to the visit side.
        $scopedVisitIds = Visit::query()->select('id');

        $trendRows = VisitPayment::query()
            ->whereIn('visit_id', $scopedVisitIds)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $startOfWindow->copy()->startOfDay())
            ->where('paid_at', '<', $today->copy()->addDay()->startOfDay())
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as t')
            ->groupBy('d')
            ->pluck('t', 'd');

        $revenueTrend = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $startOfWindow->copy()->addDays($i)->toDateString();
            $revenueTrend[] = [
                'date' => $date,
                'total' => (float) ($trendRows[$date] ?? 0),
            ];
        }

        // -----------------------------------------------------------------
        // Doctor utilization (today's top 10 by visit count)
        // -----------------------------------------------------------------
        $utilRows = Visit::query()
            ->whereDate('checked_in_at', $today->toDateString())
            ->whereNotNull('doctor_id')
            ->select('doctor_id', DB::raw('COUNT(*) as visits'))
            ->groupBy('doctor_id')
            ->orderByDesc('visits')
            ->limit(10)
            ->with('doctor:id,name')
            ->get();

        $maxVisits = (int) ($utilRows->max('visits') ?: 0);
        $doctorUtilization = $utilRows->map(fn ($row) => [
            'name' => $row->doctor->name ?? '—',
            'visits' => (int) $row->visits,
            'max' => $maxVisits,
        ])->values()->all();

        // -----------------------------------------------------------------
        // Today's bookings (limit 20, ordered by res_time)
        // -----------------------------------------------------------------
        $todayBookings = Booking::query()
            ->whereDate('res_date', $today->toDateString())
            ->with(['patient:id,name', 'doctor:id,name'])
            ->orderByRaw('res_time IS NULL, res_time ASC')
            ->limit(20)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'res_time' => $b->res_time,
                'status' => $b->status,
                'patient' => ['name' => $b->patient?->name ?? $b->name ?? null],
                'doctor' => ['name' => $b->doctor?->name],
                'checked_in' => ! is_null($b->checked_in_at),
            ])
            ->values()
            ->all();

        // -----------------------------------------------------------------
        // Recent activity (latest 15 visits updated today)
        // -----------------------------------------------------------------
        $recentActivity = Visit::query()
            ->whereDate('updated_at', $today->toDateString())
            ->with(['patient:id,name', 'doctor:id,name'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(fn (Visit $v) => [
                'id' => $v->id,
                'status' => $v->status,
                'patient_name' => $v->patient?->name,
                'doctor_name' => $v->doctor?->name,
                'updated_at' => optional($v->updated_at)->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard/Index', [
            'kpis' => $kpis,
            'revenueTrend' => $revenueTrend,
            'doctorUtilization' => $doctorUtilization,
            'todayBookings' => $todayBookings,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Sum of paid VisitPayments for a single calendar day, scoped to the
     * caller's visible visits via a Visit subselect.
     */
    protected function scopedPaymentsForDay(Carbon $day): float
    {
        $scopedVisitIds = Visit::query()->select('id');

        return (float) VisitPayment::query()
            ->whereIn('visit_id', $scopedVisitIds)
            ->where('status', 'paid')
            ->whereDate('paid_at', $day->toDateString())
            ->sum('amount');
    }

    /**
     * Percentage delta of `now` vs `prev`. Returns null if `prev` is zero
     * (avoids division-by-zero spikes that read as "infinite growth").
     */
    protected function pctDelta(float $now, float $prev): ?float
    {
        if ($prev <= 0.0) {
            return null;
        }

        return round((($now - $prev) / $prev) * 100, 1);
    }
}
