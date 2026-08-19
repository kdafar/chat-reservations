<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Doctor Productivity Report — output measured against the time it took.
 *
 * The existing reports rank doctors by the commission they earned, which mostly
 * ranks them by how many hours they were rostered. Revenue per rostered hour and
 * chair utilisation say something different: who converts their clinic time into
 * value, and who has capacity going spare.
 */
class DoctorReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_doctor_reports')) {
            abort(403, 'Not authorized to view doctor reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'from' => $request->input('from') ?: Carbon::now($tz)->subDays(29)->toDateString(),
            'to' => $request->input('to') ?: Carbon::now($tz)->toDateString(),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
        ];
        $from = Carbon::parse($filters['from'], $tz)->startOfDay();
        $to = Carbon::parse($filters['to'], $tz)->endOfDay();
        $branchIds = $this->accessibleBranchIds();

        $memo = null;
        $get = function (string $key) use (&$memo, $filters, $from, $to, $branchIds) {
            $memo ??= $this->build($filters, $from, $to, $branchIds);

            return $memo[$key];
        };

        return Inertia::render('Reports/DoctorReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'doctors'),
            'doctors' => Inertia::defer(fn () => $get('doctors'), 'doctors'),
            'utilisation' => Inertia::defer(fn () => $get('utilisation'), 'doctors'),
            'specialty_mix' => Inertia::defer(fn () => $get('specialty_mix'), 'doctors'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $scope = function ($q, string $column) use ($filters, $branchIds) {
            if ($filters['branch_id']) {
                $q->where($column, $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn($column, $branchIds ?: [0]);
            }

            return $q;
        };

        // ---- Output per doctor ------------------------------------------------
        $output = $scope(
            DB::table('visits as v')
                ->join('doctors as d', 'd.id', '=', 'v.doctor_id')
                ->where('v.status', 'completed')
                ->whereBetween('v.computed_at', [$from, $to]),
            'v.branch_id'
        )
            ->groupBy('d.id', 'd.name', 'd.specialty')
            ->selectRaw('d.id as doctor_id, d.name as doctor, d.specialty,
                COUNT(*) as visits,
                COUNT(DISTINCT v.patient_id) as patients,
                SUM(v.fees_total + v.packages_price_total + v.items_price_total - v.discount_total) as revenue,
                SUM(v.profit_total) as profit,
                AVG(v.fees_total + v.packages_price_total + v.items_price_total - v.discount_total) as avg_ticket,
                AVG(CASE WHEN v.service_started_at IS NOT NULL AND v.completed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, v.service_started_at, v.completed_at) END) as avg_minutes')
            ->get()->keyBy('doctor_id');

        // ---- Rostered hours, from the published shifts -------------------------
        $rostered = $scope(
            DB::table('doctor_shifts as s')
                ->where('s.is_cancelled', false)
                ->whereBetween('s.shift_date', [$from->toDateString(), $to->toDateString()]),
            's.branch_id'
        )
            ->groupBy('s.doctor_id')
            ->selectRaw('s.doctor_id,
                SUM(GREATEST(TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) - COALESCE(s.break_minutes,0), 0)) as minutes,
                COUNT(*) as shifts')
            ->get()->keyBy('doctor_id');

        // ---- Commission earned ---------------------------------------------------
        $commission = $scope(
            DB::table('doctor_compensation_ledgers as l')->whereBetween('l.created_at', [$from, $to]),
            'l.branch_id'
        )->groupBy('l.doctor_id')->selectRaw('l.doctor_id, SUM(l.doctor_cut_amount) as cut')
            ->pluck('cut', 'doctor_id')->all();

        // ---- Follow-up conversion — did the patient come back? --------------------
        $followUps = $scope(
            DB::table('visits as v')->where('v.status', 'completed')
                ->whereBetween('v.computed_at', [$from, $to])->whereNotNull('v.follow_up_date'),
            'v.branch_id'
        )->groupBy('v.doctor_id')->selectRaw('v.doctor_id, COUNT(*) as c')->pluck('c', 'doctor_id')->all();

        $rows = [];
        foreach ($output as $doctorId => $o) {
            $mins = (float) ($rostered[$doctorId]->minutes ?? 0);
            $hours = round($mins / 60, 1);
            $revenue = (float) $o->revenue;
            // Chair utilisation: consulting minutes actually delivered against the
            // minutes the doctor was rostered for.
            $consultMins = (float) $o->visits * (float) ($o->avg_minutes ?: 0);

            $rows[] = [
                'doctor' => (string) $o->doctor,
                'specialty' => (string) ($o->specialty ?: '—'),
                'visits' => (int) $o->visits,
                'patients' => (int) $o->patients,
                'revenue' => round($revenue, 3),
                'profit' => round((float) $o->profit, 3),
                'avg_ticket' => round((float) $o->avg_ticket, 3),
                'avg_minutes' => $o->avg_minutes !== null ? round((float) $o->avg_minutes, 1) : null,
                'commission' => round((float) ($commission[$doctorId] ?? 0), 3),
                'rostered_hours' => $hours,
                'shifts' => (int) ($rostered[$doctorId]->shifts ?? 0),
                'revenue_per_hour' => $hours > 0 ? round($revenue / $hours, 3) : null,
                'utilisation' => $mins > 0 ? round(min(100, ($consultMins / $mins) * 100), 1) : null,
                'follow_up_rate' => (int) $o->visits > 0 ? round((($followUps[$doctorId] ?? 0) / (int) $o->visits) * 100, 1) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        // ---- Headline -----------------------------------------------------------------
        $totalRevenue = array_sum(array_column($rows, 'revenue'));
        $totalHours = array_sum(array_column($rows, 'rostered_hours'));
        $withUtil = array_filter($rows, fn ($r) => $r['utilisation'] !== null);

        $kpis = [
            'doctors' => count($rows),
            'visits' => array_sum(array_column($rows, 'visits')),
            'revenue' => round($totalRevenue, 3),
            'rostered_hours' => round($totalHours, 1),
            'revenue_per_hour' => $totalHours > 0 ? round($totalRevenue / $totalHours, 3) : null,
            'avg_utilisation' => $withUtil ? round(array_sum(array_column($withUtil, 'utilisation')) / count($withUtil), 1) : null,
            'avg_minutes' => count($rows) ? round(array_sum(array_map(fn ($r) => $r['avg_minutes'] ?? 0, $rows)) / max(1, count(array_filter($rows, fn ($r) => $r['avg_minutes'] !== null))), 1) : null,
            'commission' => round(array_sum(array_column($rows, 'commission')), 3),
        ];

        // Utilisation chart data, worst first — that's where the spare capacity is.
        $utilisation = collect($rows)->filter(fn ($r) => $r['utilisation'] !== null)
            ->sortBy('utilisation')->take(15)
            ->map(fn ($r) => ['doctor' => $r['doctor'], 'utilisation' => $r['utilisation'], 'hours' => $r['rostered_hours']])
            ->values()->all();

        $specialtyMix = collect($rows)->groupBy('specialty')
            ->map(fn ($g, $spec) => [
                'specialty' => $spec,
                'revenue' => round($g->sum('revenue'), 3),
                'visits' => $g->sum('visits'),
            ])->sortByDesc('revenue')->values()->all();

        return [
            'kpis' => $kpis,
            'doctors' => $rows,
            'utilisation' => $utilisation,
            'specialty_mix' => $specialtyMix,
        ];
    }
}
