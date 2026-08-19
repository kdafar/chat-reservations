<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\ResolvesAccessibleClinics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Patient & Clinical Report — who the clinic is actually treating, whether they
 * come back, and who has quietly stopped coming.
 *
 * The other reports count money and visits; none of them count *people*. A
 * clinic that bills the same revenue from 200 loyal patients versus 800
 * one-time visitors is a completely different business, and only a patient-level
 * view shows that. Everything here is built on completed visits, since a
 * cancelled or no-show visit is not clinical contact.
 */
class PatientReportsController extends Controller
{
    /** A patient who has not been seen in this many days is treated as lapsed. */
    private const LAPSED_DAYS = 120;

    /** Window a newly-acquired patient gets to come back before the cohort counts them as lost. */
    private const RETENTION_DAYS = 90;

    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_patient_reports')) {
            abort(403, 'Not authorized to view patient reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            // 90 days, not a year. "New" means the patient's first-ever visit
            // falls inside the window, so a window wider than the clinic's whole
            // history makes every patient new and returning read as zero — true,
            // but useless as a landing view.
            'from' => $request->input('from') ?: Carbon::now($tz)->subDays(89)->toDateString(),
            'to' => $request->input('to') ?: Carbon::now($tz)->toDateString(),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
        ];
        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();

        $branchIds = $this->accessibleBranchIds();

        $payload = fn () => $this->build($filters, $from, $to, $branchIds);
        $memo = null;
        $get = function (string $key) use (&$memo, $payload) {
            $memo ??= $payload();

            return $memo[$key];
        };

        return Inertia::render('Reports/PatientReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'patients'),
            'trend' => Inertia::defer(fn () => $get('trend'), 'patients'),
            'age_bands' => Inertia::defer(fn () => $get('age_bands'), 'patients'),
            'gender_split' => Inertia::defer(fn () => $get('gender_split'), 'patients'),
            'cohorts' => Inertia::defer(fn () => $get('cohorts'), 'patients'),
            'top_patients' => Inertia::defer(fn () => $get('top_patients'), 'patients'),
            'diagnosis_mix' => Inertia::defer(fn () => $get('diagnosis_mix'), 'patients'),
            'lapsed' => Inertia::defer(fn () => $get('lapsed'), 'patients'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $rev = fn (string $t) => "(COALESCE($t.fees_total,0) + COALESCE($t.packages_price_total,0)
            + COALESCE($t.items_price_total,0) - COALESCE($t.discount_total,0))";

        $scope = function ($q, string $t) use ($filters, $branchIds) {
            $q->where("$t.status", 'completed')->whereNotNull("$t.completed_at");
            if ($filters['branch_id']) {
                $q->where("$t.branch_id", $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn("$t.branch_id", $branchIds ?: [0]);
            }

            return $q;
        };

        // Per-patient lifetime aggregate (all time, within the branch scope). Small
        // enough to finish the arithmetic in PHP, and every KPI below reads from it.
        $lifetime = $scope(DB::table('visits'), 'visits')
            ->groupBy('visits.patient_id')
            ->selectRaw('visits.patient_id as pid, COUNT(*) as visit_count, SUM('.$rev('visits').') as spend,
                MIN(visits.completed_at) as first_at, MAX(visits.completed_at) as last_at')
            ->get()->keyBy('pid');

        $seenInWindow = $scope(DB::table('visits'), 'visits')
            ->whereBetween('visits.completed_at', [$from, $to])
            ->groupBy('visits.patient_id')
            ->selectRaw('visits.patient_id as pid, COUNT(*) as visit_count, SUM('.$rev('visits').') as spend')
            ->get()->keyBy('pid');

        // ---- KPIs -------------------------------------------------------------
        $seenCount = $seenInWindow->count();
        $newCount = 0;
        $ltvSum = 0.0;
        $ltVisitsSum = 0;
        $gapDays = [];
        $repeaters = 0;

        foreach ($seenInWindow as $pid => $w) {
            $life = $lifetime[$pid] ?? null;
            if (! $life) {
                continue;
            }
            // "New" means their first-ever completed visit landed inside the window,
            // not merely that the patient record was created recently.
            $firstAt = Carbon::parse($life->first_at);
            if ($firstAt->betweenIncluded($from, $to)) {
                $newCount++;
            }
            $ltvSum += (float) $life->spend;
            $ltVisitsSum += (int) $life->visit_count;
            if ((int) $life->visit_count > 1) {
                $repeaters++;
                $span = $firstAt->diffInDays(Carbon::parse($life->last_at));
                $gapDays[] = $span / ((int) $life->visit_count - 1);
            }
        }

        $kpis = [
            'patients_seen' => $seenCount,
            'new_patients' => $newCount,
            'returning_patients' => $seenCount - $newCount,
            // Share of the window's patients who have ever come back at all — the
            // headline loyalty number, independent of how the window is cut.
            'repeat_rate' => $seenCount > 0 ? round($repeaters / $seenCount * 100, 1) : 0.0,
            'avg_ltv' => $seenCount > 0 ? round($ltvSum / $seenCount, 3) : 0.0,
            'avg_visits' => $seenCount > 0 ? round($ltVisitsSum / $seenCount, 2) : 0.0,
            'avg_gap_days' => $gapDays ? (int) round(array_sum($gapDays) / count($gapDays)) : 0,
            'visits_in_window' => (int) $seenInWindow->sum('visit_count'),
            'revenue_in_window' => round((float) $seenInWindow->sum('spend'), 3),
        ];

        // ---- New vs returning by month ----------------------------------------
        $firstsSub = $scope(DB::table('visits'), 'visits')
            ->groupBy('visits.patient_id')
            ->selectRaw('visits.patient_id as pid, MIN(visits.completed_at) as first_at');

        $trend = $scope(DB::table('visits as v'), 'v')
            ->joinSub($firstsSub, 'f', 'f.pid', '=', 'v.patient_id')
            ->whereBetween('v.completed_at', [$from, $to])
            ->groupBy(DB::raw("DATE_FORMAT(v.completed_at, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(v.completed_at, '%Y-%m') as ym,
                COUNT(DISTINCT v.patient_id) as seen_count,
                COUNT(DISTINCT CASE WHEN DATE_FORMAT(f.first_at, '%Y-%m') = DATE_FORMAT(v.completed_at, '%Y-%m')
                    THEN v.patient_id END) as new_count,
                SUM(".$rev('v').') as revenue')
            ->orderBy('ym')->get()
            ->map(fn ($r) => [
                'month' => Carbon::parse($r->ym.'-01')->format('M Y'),
                'seen' => (int) $r->seen_count,
                'new' => (int) $r->new_count,
                'returning' => (int) $r->seen_count - (int) $r->new_count,
                'revenue' => round((float) $r->revenue, 3),
            ])->all();

        // ---- Demographics ------------------------------------------------------
        $seenSub = $scope(DB::table('visits'), 'visits')
            ->whereBetween('visits.completed_at', [$from, $to])
            ->groupBy('visits.patient_id')
            ->selectRaw('visits.patient_id as pid');

        $people = DB::table('patients')
            ->joinSub($seenSub, 'sp', 'sp.pid', '=', 'patients.id')
            ->whereNull('patients.deleted_at')
            ->get(['patients.dob', 'patients.gender']);

        $bands = ['<18' => 0, '18–24' => 0, '25–34' => 0, '35–44' => 0, '45–54' => 0, '55–64' => 0, '65+' => 0, 'Unknown' => 0];
        $genders = [];
        $today = Carbon::now();
        foreach ($people as $p) {
            $key = 'Unknown';
            if ($p->dob) {
                $age = Carbon::parse($p->dob)->diffInYears($today);
                $key = match (true) {
                    $age < 18 => '<18',
                    $age < 25 => '18–24',
                    $age < 35 => '25–34',
                    $age < 45 => '35–44',
                    $age < 55 => '45–54',
                    $age < 65 => '55–64',
                    default => '65+',
                };
            }
            $bands[$key]++;
            $g = $p->gender ?: 'unknown';
            $genders[$g] = ($genders[$g] ?? 0) + 1;
        }

        $ageBands = [];
        foreach ($bands as $label => $count) {
            if ($count > 0 || $label !== 'Unknown') {
                $ageBands[] = ['band' => $label, 'count' => $count];
            }
        }
        arsort($genders);
        $genderSplit = [];
        foreach ($genders as $g => $c) {
            $genderSplit[] = ['gender' => $g, 'count' => $c];
        }

        // ---- Retention cohorts --------------------------------------------------
        // Of the patients acquired in month X, how many came back within 90 days.
        // Anything acquired inside the last 90 days has not had its full chance yet,
        // so it is flagged immature rather than dragging the retention line down.
        $secondSub = $scope(DB::table('visits as v2'), 'v2')
            ->joinSub(
                $scope(DB::table('visits'), 'visits')
                    ->groupBy('visits.patient_id')
                    ->selectRaw('visits.patient_id as pid, MIN(visits.completed_at) as first_at'),
                'f2',
                'f2.pid',
                '=',
                'v2.patient_id'
            )
            ->whereColumn('v2.completed_at', '>', 'f2.first_at')
            ->groupBy('v2.patient_id')
            ->selectRaw('v2.patient_id as pid, MIN(v2.completed_at) as second_at');

        $cohortFirsts = $scope(DB::table('visits'), 'visits')
            ->groupBy('visits.patient_id')
            ->selectRaw('visits.patient_id as pid, MIN(visits.completed_at) as first_at');

        $matureBefore = Carbon::now()->subDays(self::RETENTION_DAYS);

        $cohorts = DB::query()->fromSub($cohortFirsts, 'f')
            ->leftJoinSub($secondSub, 's', 's.pid', '=', 'f.pid')
            ->whereBetween('f.first_at', [$from, $to])
            ->groupBy(DB::raw("DATE_FORMAT(f.first_at, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(f.first_at, '%Y-%m') as ym, COUNT(*) as cohort_size,
                SUM(CASE WHEN s.second_at IS NOT NULL
                    AND s.second_at <= DATE_ADD(f.first_at, INTERVAL ".self::RETENTION_DAYS.' DAY)
                    THEN 1 ELSE 0 END) as returned_count')
            ->orderBy('ym')->get()
            ->map(function ($r) use ($matureBefore) {
                $size = (int) $r->cohort_size;
                $ret = (int) $r->returned_count;

                return [
                    'month' => Carbon::parse($r->ym.'-01')->format('M Y'),
                    'size' => $size,
                    'returned' => $ret,
                    'rate' => $size > 0 ? round($ret / $size * 100, 1) : 0.0,
                    'mature' => Carbon::parse($r->ym.'-01')->endOfMonth()->lte($matureBefore),
                ];
            })->all();

        // ---- Top patients by lifetime spend --------------------------------------
        $topPatients = $scope(DB::table('visits'), 'visits')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->whereNull('patients.deleted_at')
            ->groupBy('patients.id', 'patients.name', 'patients.phone')
            ->selectRaw('patients.name as patient, patients.phone as phone,
                COUNT(*) as visit_count, SUM('.$rev('visits').') as spend,
                MAX(visits.completed_at) as last_at')
            ->orderByDesc('spend')->limit(15)->get()
            ->map(fn ($r) => [
                'patient' => $this->name($r->patient),
                'phone' => (string) ($r->phone ?: ''),
                'visits' => (int) $r->visit_count,
                'spend' => round((float) $r->spend, 3),
                'last_visit' => $r->last_at ? Carbon::parse($r->last_at)->format('d M Y') : '—',
            ])->all();

        // ---- Treatment / diagnosis mix ---------------------------------------------
        $diagnosisMix = $scope(DB::table('visits'), 'visits')
            ->whereBetween('visits.completed_at', [$from, $to])
            ->whereNotNull('visits.diagnosis')
            ->where('visits.diagnosis', '<>', '')
            ->groupBy('visits.diagnosis')
            ->selectRaw('visits.diagnosis as diagnosis, COUNT(*) as visit_count,
                COUNT(DISTINCT visits.patient_id) as patient_count, SUM('.$rev('visits').') as revenue')
            ->orderByDesc('visit_count')->limit(15)->get()
            ->map(fn ($r) => [
                'diagnosis' => trim((string) $r->diagnosis),
                'visits' => (int) $r->visit_count,
                'patients' => (int) $r->patient_count,
                'revenue' => round((float) $r->revenue, 3),
            ])->all();

        // ---- Recall list -----------------------------------------------------------
        // Lapsed is measured from today, not from the filter window: a recall list is
        // only useful if it answers "who should we call now".
        $lapsedCutoff = Carbon::now()->subDays(self::LAPSED_DAYS);

        $lapsed = $scope(DB::table('visits'), 'visits')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->whereNull('patients.deleted_at')
            ->groupBy('patients.id', 'patients.name', 'patients.phone')
            ->havingRaw('MAX(visits.completed_at) < ?', [$lapsedCutoff])
            ->selectRaw('patients.name as patient, patients.phone as phone,
                COUNT(*) as visit_count, SUM('.$rev('visits').') as spend,
                MAX(visits.completed_at) as last_at')
            ->orderByDesc('spend')->limit(40)->get()
            ->map(fn ($r) => [
                'patient' => $this->name($r->patient),
                'phone' => (string) ($r->phone ?: ''),
                'visits' => (int) $r->visit_count,
                'spend' => round((float) $r->spend, 3),
                'last_visit' => $r->last_at ? Carbon::parse($r->last_at)->format('d M Y') : '—',
                'days_since' => $r->last_at ? (int) Carbon::parse($r->last_at)->diffInDays(Carbon::now()) : 0,
            ])->all();

        $kpis['lapsed_count'] = $lifetime->filter(fn ($l) => Carbon::parse($l->last_at)->lt($lapsedCutoff))->count();
        $kpis['lapsed_value'] = round((float) $lifetime->filter(fn ($l) => Carbon::parse($l->last_at)->lt($lapsedCutoff))->sum('spend'), 3);

        return [
            'kpis' => $kpis,
            'trend' => $trend,
            'age_bands' => $ageBands,
            'gender_split' => $genderSplit,
            'cohorts' => $cohorts,
            'top_patients' => $topPatients,
            'diagnosis_mix' => $diagnosisMix,
            'lapsed' => $lapsed,
        ];
    }

    /** Some names are stored as {en,ar} JSON blobs. */
    protected function name($value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $d = json_decode($value, true);
            if (is_array($d)) {
                return $d[app()->getLocale()] ?? $d['en'] ?? (array_values($d)[0] ?? '—');
            }
        }

        return (string) ($value ?? '—');
    }
}
