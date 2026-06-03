<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inpatient Reports — v2 replacement for the Filament InpatientReports widgets
 * (KPI stats, 30-day bed occupancy trend, admissions by ward, bed revenue per ward).
 */
class InpatientReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! ($u->hasRole(['admin', 'super_admin', 'clinic_admin']) || $u->can('view_any_admissions'))) {
            abort(403, 'Not authorized to view inpatient reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $monthStart = Carbon::now($tz)->startOfMonth();

        // Streamed after the shell via deferred props (one follow-up request).
        return Inertia::render('Reports/InpatientReports', [
            'kpis' => Inertia::defer(fn () => $this->kpis($monthStart), 'inpatient'),
            'occupancy_trend' => Inertia::defer(fn () => $this->occupancyTrend($tz), 'inpatient'),
            'admissions_by_ward' => Inertia::defer(fn () => $this->admissionsByWard(), 'inpatient'),
            'revenue_per_ward' => Inertia::defer(fn () => $this->revenuePerWard($monthStart), 'inpatient'),
        ]);
    }

    protected function kpis(Carbon $monthStart): array
    {
        // ALOS over discharges in the last 30 days.
        $discharges = DB::table('admissions')
            ->whereNotNull('discharged_at')
            ->where('discharged_at', '>=', now()->subDays(30))
            ->get(['admitted_at', 'discharged_at']);
        $alos = 0.0;
        if ($discharges->count() > 0) {
            $sum = 0.0;
            foreach ($discharges as $d) {
                $sum += Carbon::parse($d->admitted_at)->floatDiffInDays(Carbon::parse($d->discharged_at));
            }
            $alos = $sum / $discharges->count();
        }

        return [
            'alos' => round($alos, 1),
            'alos_count' => $discharges->count(),
            'admissions_month' => (int) DB::table('admissions')->where('admitted_at', '>=', $monthStart)->count(),
            'bed_revenue_month' => round((float) DB::table('admission_charges')
                ->where('source', 'bed_day')->where('charge_date', '>=', $monthStart->toDateString())->sum('amount'), 3),
            'active_now' => (int) DB::table('admissions')->where('status', 'active')->count(),
        ];
    }

    protected function occupancyTrend(string $tz): array
    {
        $totalBeds = max(1, (int) DB::table('beds')->count());
        $out = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::now($tz)->subDays($i)->endOfDay();
            $occupied = (int) DB::table('admission_bed_stays')
                ->where('assigned_at', '<=', $day)
                ->where(fn ($q) => $q->whereNull('released_at')->orWhere('released_at', '>', $day))
                ->distinct('bed_id')->count('bed_id');
            $out[] = [
                'label' => $day->format('M j'),
                'occupancy' => round($occupied / $totalBeds * 100, 1),
                'occupied' => $occupied,
            ];
        }
        return $out;
    }

    protected function admissionsByWard(): array
    {
        return DB::table('admission_bed_stays as s')
            ->join('admissions as a', 'a.id', '=', 's.admission_id')
            ->join('wards as w', 'w.id', '=', 's.ward_id')
            ->where('a.status', 'active')->whereNull('s.released_at')
            ->groupBy('w.id', 'w.name')
            ->selectRaw('w.name as ward, COUNT(*) as cnt')
            ->orderByDesc('cnt')->get()
            ->map(fn ($r) => ['ward' => $this->wardName($r->ward), 'count' => (int) $r->cnt])->all();
    }

    protected function revenuePerWard(Carbon $monthStart): array
    {
        return DB::table('admission_charges as c')
            ->leftJoin('admission_bed_stays as s', 's.id', '=', 'c.bed_stay_id')
            ->leftJoin('wards as w', 'w.id', '=', 's.ward_id')
            ->where('c.source', 'bed_day')->where('c.charge_date', '>=', $monthStart->toDateString())
            ->groupBy('w.id', 'w.name')
            ->selectRaw("COALESCE(w.name, '(no ward)') as ward, SUM(c.amount) as revenue")
            ->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['ward' => $this->wardName($r->ward), 'revenue' => round((float) $r->revenue, 3)])->all();
    }

    /** Ward name may be a JSON {en,ar} blob. */
    protected function wardName($name): string
    {
        if (is_string($name) && str_starts_with(trim($name), '{')) {
            $d = json_decode($name, true);
            if (is_array($d)) return $d[app()->getLocale()] ?? $d['en'] ?? array_values($d)[0] ?? '—';
        }
        return (string) ($name ?? '—');
    }
}
