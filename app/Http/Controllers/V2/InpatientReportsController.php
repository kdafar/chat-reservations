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
        if (! $u || ! ($u->hasRole(['admin', 'super_admin', 'clinic_admin', 'accountant']) || $u->can('view_any_admissions'))) {
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
            'discharge_outcomes' => Inertia::defer(fn () => $this->dischargeOutcomes($monthStart), 'inpatient'),
            'los_distribution' => Inertia::defer(fn () => $this->losDistribution($monthStart), 'inpatient'),
            'readmission' => Inertia::defer(fn () => $this->readmission($tz), 'inpatient'),
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

        // Previous month, for comparison.
        $prevStart = (clone $monthStart)->subMonthNoOverflow();
        $prevEnd = (clone $monthStart)->subSecond();
        $admissionsMonth = (int) DB::table('admissions')->where('admitted_at', '>=', $monthStart)->count();
        $admissionsPrev = (int) DB::table('admissions')->whereBetween('admitted_at', [$prevStart, $prevEnd])->count();
        $bedRevMonth = round((float) DB::table('admission_charges')
            ->where('source', 'bed_day')->where('charge_date', '>=', $monthStart->toDateString())->sum('amount'), 3);
        $bedRevPrev = round((float) DB::table('admission_charges')
            ->where('source', 'bed_day')->whereBetween('charge_date', [$prevStart->toDateString(), $prevEnd->toDateString()])->sum('amount'), 3);
        $pct = fn ($c, $p) => $p > 0 ? round((($c - $p) / $p) * 100, 1) : null;

        return [
            'alos' => round($alos, 1),
            'alos_count' => $discharges->count(),
            'admissions_month' => $admissionsMonth,
            'admissions_change' => $pct($admissionsMonth, $admissionsPrev),
            'bed_revenue_month' => $bedRevMonth,
            'bed_revenue_change' => $pct($bedRevMonth, $bedRevPrev),
            'active_now' => (int) DB::table('admissions')->where('status', 'active')->count(),
        ];
    }

    /** Discharge outcomes (discharged / LAMA / transferred / expired) this month. */
    protected function dischargeOutcomes(Carbon $monthStart): array
    {
        return DB::table('admissions')->whereNotNull('discharged_at')->where('discharged_at', '>=', $monthStart)
            ->groupBy('status')->selectRaw('status, COUNT(*) as c')->get()
            ->map(fn ($r) => ['status' => (string) ($r->status ?: 'discharged'), 'count' => (int) $r->c])->all();
    }

    /** Length-of-stay distribution for discharges this month. */
    protected function losDistribution(Carbon $monthStart): array
    {
        $rows = DB::table('admissions')->whereNotNull('discharged_at')->where('discharged_at', '>=', $monthStart)
            ->get(['admitted_at', 'discharged_at']);
        $buckets = ['0–3' => 0, '4–7' => 0, '8–14' => 0, '15+' => 0];
        foreach ($rows as $r) {
            $d = Carbon::parse($r->admitted_at)->floatDiffInDays(Carbon::parse($r->discharged_at));
            if ($d <= 3) $buckets['0–3']++;
            elseif ($d <= 7) $buckets['4–7']++;
            elseif ($d <= 14) $buckets['8–14']++;
            else $buckets['15+']++;
        }
        return array_map(fn ($label, $count) => ['label' => $label, 'count' => $count], array_keys($buckets), array_values($buckets));
    }

    /** 30-day readmission rate over the last 90 days of activity. */
    protected function readmission(string $tz): array
    {
        $since = Carbon::now($tz)->subDays(90);
        $counts = DB::table('admissions')->where('admitted_at', '>=', $since)->whereNotNull('patient_id')
            ->groupBy('patient_id')->selectRaw('patient_id, COUNT(*) as c')->get();
        $patients = $counts->count();
        $readmitted = $counts->where('c', '>', 1)->count();
        return [
            'rate' => $patients > 0 ? round(($readmitted / $patients) * 100, 1) : 0,
            'readmitted' => $readmitted,
            'patients' => $patients,
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
