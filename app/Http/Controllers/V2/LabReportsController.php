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
 * Laboratory Report — how fast the lab turns work around, and where it stalls.
 *
 * The lab worklist shows individual orders but never aggregates them, so nobody
 * could answer "how long does a result actually take" or "which step is the
 * bottleneck". Every lab order carries a full timestamp chain
 * (ordered → sample collected → started → completed → reviewed → delivered),
 * so the turnaround can be decomposed per stage rather than reported as one
 * opaque total — that is what tells the manager which step to fix.
 */
class LabReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_lab_reports')) {
            abort(403, 'Not authorized to view laboratory reports.');
        }

        $tz = config('app.timezone', 'Asia/Kuwait');
        $filters = [
            'from' => $request->input('from') ?: Carbon::now($tz)->subMonths(6)->startOfMonth()->toDateString(),
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

        return Inertia::render('Reports/LabReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'lab'),
            'stages' => Inertia::defer(fn () => $get('stages'), 'lab'),
            'tat_buckets' => Inertia::defer(fn () => $get('tat_buckets'), 'lab'),
            'top_tests' => Inertia::defer(fn () => $get('top_tests'), 'lab'),
            'flag_mix' => Inertia::defer(fn () => $get('flag_mix'), 'lab'),
            'backlog' => Inertia::defer(fn () => $get('backlog'), 'lab'),
            'by_doctor' => Inertia::defer(fn () => $get('by_doctor'), 'lab'),
            'monthly' => Inertia::defer(fn () => $get('monthly'), 'lab'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? $this->name($b->name)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $scope = function ($q) use ($filters, $branchIds) {
            $q->whereNull('lab_orders.deleted_at');
            if ($filters['branch_id']) {
                $q->where('lab_orders.branch_id', $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn('lab_orders.branch_id', $branchIds ?: [0]);
            }

            return $q;
        };
        // Orders raised inside the window, cancellations excluded from volume math.
        $inWindow = fn ($q) => $scope($q)
            ->whereBetween('lab_orders.ordered_at', [$from, $to])
            ->where('lab_orders.status', '!=', 'cancelled');

        // ---- Headline counts --------------------------------------------------
        $orderCount = (int) $inWindow(DB::table('lab_orders'))->count();

        $itemAgg = $inWindow(
            DB::table('lab_order_items')->join('lab_orders', 'lab_orders.id', '=', 'lab_order_items.lab_order_id')
        )
            ->where('lab_order_items.status', '!=', 'cancelled')
            ->selectRaw("COUNT(*) as items_total,
                SUM(CASE WHEN lab_order_items.status = 'completed' THEN 1 ELSE 0 END) as items_done,
                SUM(COALESCE(lab_order_items.price_snapshot, 0)) as revenue,
                SUM(CASE WHEN lab_order_items.flag IN ('high','low','critical') THEN 1 ELSE 0 END) as abnormal,
                SUM(CASE WHEN lab_order_items.flag IN ('normal','high','low','critical') THEN 1 ELSE 0 END) as flagged")
            ->first();

        $flagged = (int) ($itemAgg->flagged ?? 0);
        $abnormalRate = $flagged > 0 ? round(((int) $itemAgg->abnormal / $flagged) * 100, 1) : 0.0;

        // Overall turnaround, order-level, only where the chain actually closed.
        $tatRow = $inWindow(DB::table('lab_orders'))
            ->whereNotNull('lab_orders.completed_at')
            ->whereColumn('lab_orders.completed_at', '>=', 'lab_orders.ordered_at')
            ->selectRaw('COUNT(*) as n, AVG(TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at)) as mins,
                MAX(TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at)) as worst')
            ->first();

        // ---- Backlog: open orders as of the period end, not just the window ----
        // An order raised before `from` and still open is exactly the thing a
        // backlog panel exists to surface, so it is deliberately not date-boxed.
        $backlogBase = fn () => $scope(DB::table('lab_orders'))
            ->where('lab_orders.ordered_at', '<=', $to)
            ->whereNull('lab_orders.completed_at')
            ->whereNotIn('lab_orders.status', ['completed', 'cancelled']);

        $backlogRow = $backlogBase()
            ->selectRaw('COUNT(*) as n,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?) < 24 THEN 1 ELSE 0 END) as b_lt1d,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?) >= 24 AND TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?) < 72 THEN 1 ELSE 0 END) as b_1_3d,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?) >= 72 AND TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?) < 168 THEN 1 ELSE 0 END) as b_3_7d,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?) >= 168 THEN 1 ELSE 0 END) as b_7dp,
                MAX(TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?)) as oldest_hours', array_fill(0, 7, $to))
            ->first();

        $kpis = [
            'orders' => $orderCount,
            'tests_done' => (int) ($itemAgg->items_done ?? 0),
            'tests_total' => (int) ($itemAgg->items_total ?? 0),
            'avg_tat_hours' => $tatRow && $tatRow->n > 0 ? round(((float) $tatRow->mins) / 60, 2) : null,
            'worst_tat_hours' => $tatRow && $tatRow->n > 0 ? round(((float) $tatRow->worst) / 60, 2) : null,
            'completed_orders' => (int) ($tatRow->n ?? 0),
            'abnormal_rate' => $abnormalRate,
            'abnormal_count' => (int) ($itemAgg->abnormal ?? 0),
            'backlog' => (int) ($backlogRow->n ?? 0),
            'oldest_backlog_hours' => $backlogRow && $backlogRow->n > 0 ? round((float) $backlogRow->oldest_hours, 2) : null,
            'revenue' => round((float) ($itemAgg->revenue ?? 0), 3),
        ];

        // ---- Where the time goes ----------------------------------------------
        // Each stage measures from the latest timestamp that actually exists
        // before it, so a lab that skips "sample collected" still yields a real
        // processing time instead of dropping the order from the analysis. `n`
        // travels with every stage so a mean built on two orders reads as such.
        $stageDefs = [
            ['key' => 'collect', 'start' => 'lab_orders.ordered_at', 'end' => 'lab_orders.sample_collected_at'],
            ['key' => 'start', 'start' => 'COALESCE(lab_orders.sample_collected_at, lab_orders.ordered_at)', 'end' => 'lab_orders.started_at'],
            ['key' => 'analyse', 'start' => 'COALESCE(lab_orders.started_at, lab_orders.sample_collected_at, lab_orders.ordered_at)', 'end' => 'lab_orders.completed_at'],
            ['key' => 'review', 'start' => 'lab_orders.completed_at', 'end' => 'lab_orders.reviewed_at'],
            ['key' => 'deliver', 'start' => 'COALESCE(lab_orders.reviewed_at, lab_orders.completed_at)', 'end' => 'lab_orders.delivered_at'],
        ];
        $selects = [];
        foreach ($stageDefs as $s) {
            $guard = "{$s['end']} IS NOT NULL AND {$s['end']} >= {$s['start']}";
            $selects[] = "AVG(CASE WHEN {$guard} THEN TIMESTAMPDIFF(MINUTE, {$s['start']}, {$s['end']}) END) as {$s['key']}_mins";
            $selects[] = "SUM(CASE WHEN {$guard} THEN 1 ELSE 0 END) as {$s['key']}_n";
        }
        $stageRow = $inWindow(DB::table('lab_orders'))->selectRaw(implode(', ', $selects))->first();

        $stages = [];
        foreach ($stageDefs as $s) {
            $n = (int) ($stageRow->{$s['key'].'_n'} ?? 0);
            $stages[] = [
                'key' => $s['key'],
                'hours' => $n > 0 ? round(((float) $stageRow->{$s['key'].'_mins'}) / 60, 2) : null,
                'n' => $n,
            ];
        }

        // ---- Turnaround distribution -------------------------------------------
        $bucketRow = $inWindow(DB::table('lab_orders'))
            ->whereNotNull('lab_orders.completed_at')
            ->whereColumn('lab_orders.completed_at', '>=', 'lab_orders.ordered_at')
            ->selectRaw('SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at) < 240 THEN 1 ELSE 0 END) as t_lt4,
                SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at) BETWEEN 240 AND 719 THEN 1 ELSE 0 END) as t_4_12,
                SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at) BETWEEN 720 AND 1439 THEN 1 ELSE 0 END) as t_12_24,
                SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at) BETWEEN 1440 AND 4319 THEN 1 ELSE 0 END) as t_1_3d,
                SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at) >= 4320 THEN 1 ELSE 0 END) as t_3dp')
            ->first();

        $tatBuckets = [
            ['key' => 'lt4', 'count' => (int) ($bucketRow->t_lt4 ?? 0)],
            ['key' => 'h4_12', 'count' => (int) ($bucketRow->t_4_12 ?? 0)],
            ['key' => 'h12_24', 'count' => (int) ($bucketRow->t_12_24 ?? 0)],
            ['key' => 'd1_3', 'count' => (int) ($bucketRow->t_1_3d ?? 0)],
            ['key' => 'd3p', 'count' => (int) ($bucketRow->t_3dp ?? 0)],
        ];
        if (! array_sum(array_column($tatBuckets, 'count'))) {
            $tatBuckets = [];
        }

        $backlogBuckets = [
            ['key' => 'lt1d', 'count' => (int) ($backlogRow->b_lt1d ?? 0)],
            ['key' => 'd1_3', 'count' => (int) ($backlogRow->b_1_3d ?? 0)],
            ['key' => 'd3_7', 'count' => (int) ($backlogRow->b_3_7d ?? 0)],
            ['key' => 'd7p', 'count' => (int) ($backlogRow->b_7dp ?? 0)],
        ];
        if (! array_sum(array_column($backlogBuckets, 'count'))) {
            $backlogBuckets = [];
        }

        $backlogList = $backlogBase()
            ->leftJoin('doctors', 'doctors.id', '=', 'lab_orders.doctor_id')
            ->selectRaw('lab_orders.order_code as code, lab_orders.status as status, lab_orders.priority as priority,
                lab_orders.ordered_at as ordered_at, doctors.name as doctor,
                (SELECT COUNT(*) FROM lab_order_items loi WHERE loi.lab_order_id = lab_orders.id) as test_count,
                TIMESTAMPDIFF(HOUR, lab_orders.ordered_at, ?) as open_hours', [$to])
            ->orderByDesc('open_hours')->limit(25)->get()
            ->map(fn ($r) => [
                'code' => (string) ($r->code ?: '—'),
                'status' => (string) $r->status,
                'priority' => (string) ($r->priority ?: ''),
                'doctor' => $this->name($r->doctor),
                'tests' => (int) $r->test_count,
                'ordered_at' => $r->ordered_at ? Carbon::parse($r->ordered_at)->format('d M Y H:i') : '—',
                'open_hours' => round((float) $r->open_hours, 1),
            ])->all();

        // ---- Test volume + per-test abnormal rate --------------------------------
        $topTests = $inWindow(
            DB::table('lab_order_items')
                ->join('lab_orders', 'lab_orders.id', '=', 'lab_order_items.lab_order_id')
                ->join('lab_tests', 'lab_tests.id', '=', 'lab_order_items.lab_test_id')
        )
            ->where('lab_order_items.status', '!=', 'cancelled')
            ->groupBy('lab_tests.id', 'lab_tests.name', 'lab_tests.code')
            ->selectRaw("lab_tests.name as test, lab_tests.code as code, COUNT(*) as c,
                SUM(CASE WHEN lab_order_items.flag IN ('high','low','critical') THEN 1 ELSE 0 END) as abnormal,
                SUM(CASE WHEN lab_order_items.flag IN ('normal','high','low','critical') THEN 1 ELSE 0 END) as flagged,
                SUM(COALESCE(lab_order_items.price_snapshot, 0)) as revenue")
            ->orderByDesc('c')->limit(15)->get()
            ->map(fn ($r) => [
                'test' => $this->name($r->test),
                'code' => (string) ($r->code ?: ''),
                'count' => (int) $r->c,
                'abnormal' => (int) $r->abnormal,
                'abnormal_rate' => (int) $r->flagged > 0 ? round(((int) $r->abnormal / (int) $r->flagged) * 100, 1) : null,
                'revenue' => round((float) $r->revenue, 3),
            ])->all();

        // ---- Result flag mix ------------------------------------------------------
        $flagMix = $inWindow(
            DB::table('lab_order_items')->join('lab_orders', 'lab_orders.id', '=', 'lab_order_items.lab_order_id')
        )
            ->where('lab_order_items.status', '!=', 'cancelled')
            ->groupBy('lab_order_items.flag')
            ->selectRaw('lab_order_items.flag as flag, COUNT(*) as c')
            ->orderByDesc('c')->get()
            // A blank flag is a completed item nobody assessed against a range;
            // it is its own category, not a "normal".
            ->map(fn ($r) => ['flag' => ((string) $r->flag) !== '' ? (string) $r->flag : 'unassessed', 'count' => (int) $r->c])
            ->all();

        // ---- Who orders the tests --------------------------------------------------
        $byDoctor = $inWindow(
            DB::table('lab_orders')->leftJoin('doctors', 'doctors.id', '=', 'lab_orders.doctor_id')
        )
            ->groupBy('doctors.id', 'doctors.name')
            ->selectRaw('doctors.name as doctor, COUNT(*) as c,
                AVG(CASE WHEN lab_orders.completed_at IS NOT NULL AND lab_orders.completed_at >= lab_orders.ordered_at
                    THEN TIMESTAMPDIFF(MINUTE, lab_orders.ordered_at, lab_orders.completed_at) END) as mins')
            ->orderByDesc('c')->limit(12)->get()
            ->map(fn ($r) => [
                'doctor' => $this->name($r->doctor),
                'count' => (int) $r->c,
                'avg_tat_hours' => $r->mins !== null ? round(((float) $r->mins) / 60, 2) : null,
            ])->all();

        // ---- Monthly trend ------------------------------------------------------------
        $monthly = $inWindow(
            DB::table('lab_orders')
                ->leftJoin('lab_order_items', function ($j) {
                    $j->on('lab_order_items.lab_order_id', '=', 'lab_orders.id')
                        ->where('lab_order_items.status', '!=', 'cancelled');
                })
        )
            ->groupBy(DB::raw("DATE_FORMAT(lab_orders.ordered_at, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(lab_orders.ordered_at, '%Y-%m') as ym,
                COUNT(DISTINCT lab_orders.id) as orders,
                COUNT(lab_order_items.id) as tests,
                SUM(COALESCE(lab_order_items.price_snapshot, 0)) as revenue")
            ->orderBy('ym')->get()
            ->map(fn ($r) => [
                'month' => Carbon::createFromFormat('Y-m-d', $r->ym.'-01')->format('M Y'),
                'orders' => (int) $r->orders,
                'tests' => (int) $r->tests,
                'revenue' => round((float) $r->revenue, 3),
            ])->all();

        return [
            'kpis' => $kpis,
            'stages' => $stages,
            'tat_buckets' => $tatBuckets,
            'top_tests' => $topTests,
            'flag_mix' => $flagMix,
            'backlog' => ['buckets' => $backlogBuckets, 'orders' => $backlogList],
            'by_doctor' => $byDoctor,
            'monthly' => $monthly,
        ];
    }

    /** Some catalog names are stored as {en,ar} JSON blobs, others as plain strings. */
    protected function name($value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $d = json_decode($value, true);
            if (is_array($d)) {
                return $d[app()->getLocale()] ?? $d['en'] ?? (array_values($d)[0] ?? '—');
            }
        }

        return (string) ($value ?? '—') ?: '—';
    }
}
