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
 * Purchasing & Vendor Report — where the buying money goes and who is owed.
 *
 * Purchasing had a single tile on the executive dashboard. This gives the buyer
 * the three things that change a decision: which vendors take the spend, where
 * orders are stuck between raising and paying, and whether the price paid for an
 * item has been drifting.
 */
class PurchasingReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_purchasing_reports')) {
            abort(403, 'Not authorized to view purchasing reports.');
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

        $memo = null;
        $get = function (string $key) use (&$memo, $filters, $from, $to, $branchIds) {
            $memo ??= $this->build($filters, $from, $to, $branchIds);

            return $memo[$key];
        };

        return Inertia::render('Reports/PurchasingReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'purchasing'),
            'by_vendor' => Inertia::defer(fn () => $get('by_vendor'), 'purchasing'),
            'pipeline' => Inertia::defer(fn () => $get('pipeline'), 'purchasing'),
            'ap_aging' => Inertia::defer(fn () => $get('ap_aging'), 'purchasing'),
            'top_items' => Inertia::defer(fn () => $get('top_items'), 'purchasing'),
            'price_drift' => Inertia::defer(fn () => $get('price_drift'), 'purchasing'),
            'trend' => Inertia::defer(fn () => $get('trend'), 'purchasing'),
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

        $orders = fn () => $scope(
            DB::table('purchase_orders as po')->whereNull('po.deleted_at')->whereBetween('po.order_date', [$from->toDateString(), $to->toDateString()]),
            'po.branch_id'
        );

        // ---- Headline ---------------------------------------------------------
        $totals = $orders()->selectRaw('COUNT(*) as pos, COALESCE(SUM(po.total),0) as ordered,
            COALESCE(SUM(po.goods_total_kwd),0) as goods, COALESCE(SUM(po.landed_total),0) as landed')->first();

        $received = round((float) $scope(
            DB::table('purchase_receipts as r')->whereNull('r.reversed_at')->whereBetween('r.received_at', [$from, $to]),
            'r.branch_id'
        )->sum('r.landed_amount'), 3);

        $paid = round((float) $scope(
            DB::table('purchase_payments as p')->whereNull('p.deleted_at')->whereBetween('p.payment_date', [$from->toDateString(), $to->toDateString()]),
            'p.branch_id'
        )->sum('p.amount'), 3);

        // Payables straight from the control account, so the report agrees with
        // the balance sheet rather than re-deriving it from order totals.
        $apBalance = round((float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('chart_of_accounts.code', '2110')
            ->sum(DB::raw('journal_entry_lines.credit - journal_entry_lines.debit')), 3);

        $kpis = [
            'pos' => (int) $totals->pos,
            'ordered' => round((float) $totals->ordered, 3),
            'goods' => round((float) $totals->goods, 3),
            'landed' => round((float) $totals->landed, 3),
            'received' => $received,
            'paid' => $paid,
            'ap_balance' => $apBalance,
            'avg_po' => $totals->pos > 0 ? round((float) $totals->ordered / $totals->pos, 3) : 0,
            // Landed cost above the goods value is freight, customs and clearance —
            // the part of the buying price that is easy to forget when pricing.
            'landed_uplift' => (float) $totals->goods > 0
                ? round(((((float) $totals->landed) - ((float) $totals->goods)) / (float) $totals->goods) * 100, 1)
                : null,
        ];

        // ---- Vendor spend ------------------------------------------------------
        $byVendor = $orders()
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->groupBy('v.id', 'v.name')
            ->selectRaw("COALESCE(v.name, '(no vendor)') as vendor, v.id as vendor_id,
                COUNT(*) as pos, COALESCE(SUM(po.total),0) as ordered,
                COALESCE(SUM(po.landed_total - po.goods_total_kwd),0) as landed_extra")
            ->orderByDesc('ordered')->get();

        $paidByVendor = $scope(
            DB::table('purchase_payments as p')->whereNull('p.deleted_at')->whereBetween('p.payment_date', [$from->toDateString(), $to->toDateString()]),
            'p.branch_id'
        )->groupBy('p.vendor_id')->selectRaw('p.vendor_id, SUM(p.amount) as paid')->pluck('paid', 'vendor_id')->all();

        $vendorRows = $byVendor->map(function ($r) use ($paidByVendor) {
            $ordered = (float) $r->ordered;
            $paidV = (float) ($paidByVendor[$r->vendor_id] ?? 0);

            return [
                'vendor' => $this->name($r->vendor),
                'pos' => (int) $r->pos,
                'ordered' => round($ordered, 3),
                'paid' => round($paidV, 3),
                'outstanding' => round(max(0, $ordered - $paidV), 3),
                'landed_extra' => round((float) $r->landed_extra, 3),
                'share' => 0.0,
            ];
        })->all();

        $spendTotal = array_sum(array_column($vendorRows, 'ordered'));
        foreach ($vendorRows as &$row) {
            $row['share'] = $spendTotal > 0 ? round(($row['ordered'] / $spendTotal) * 100, 1) : 0;
        }
        unset($row);

        // ---- Where orders are stuck ---------------------------------------------
        $pipeline = $orders()->groupBy('po.status')
            ->selectRaw('po.status as status, COUNT(*) as c, COALESCE(SUM(po.total),0) as value')
            ->orderByDesc('value')->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->c, 'value' => round((float) $r->value, 3)])->all();

        // ---- Payables aging -------------------------------------------------------
        // Aged from the payment due date where the vendor set terms, else the order date.
        $openOrders = $scope(
            DB::table('purchase_orders as po')->whereNull('po.deleted_at')
                ->whereNotIn('po.status', ['draft', 'cancelled', 'rejected']),
            'po.branch_id'
        )->select('po.id', 'po.total', 'po.order_date', 'po.payment_due_date')->get();

        $paidPerOrder = DB::table('purchase_payments')->whereNull('deleted_at')
            ->groupBy('purchase_order_id')->selectRaw('purchase_order_id, SUM(amount) as p')
            ->pluck('p', 'purchase_order_id')->all();

        $buckets = ['Current' => 0.0, '1–30' => 0.0, '31–60' => 0.0, '60+' => 0.0];
        $counts = ['Current' => 0, '1–30' => 0, '31–60' => 0, '60+' => 0];
        foreach ($openOrders as $po) {
            $balance = round((float) $po->total - (float) ($paidPerOrder[$po->id] ?? 0), 3);
            if ($balance <= 0.005) {
                continue;
            }
            $due = Carbon::parse($po->payment_due_date ?? $po->order_date);
            $overdue = $due->isPast() ? $due->diffInDays(Carbon::now()) : 0;
            $key = match (true) {
                $overdue <= 0 => 'Current',
                $overdue <= 30 => '1–30',
                $overdue <= 60 => '31–60',
                default => '60+',
            };
            $buckets[$key] += $balance;
            $counts[$key]++;
        }
        $apAging = array_map(
            fn ($label) => ['label' => $label, 'value' => round($buckets[$label], 3), 'count' => $counts[$label]],
            array_keys($buckets)
        );

        // ---- What we buy most of -----------------------------------------------------
        $topItems = $scope(
            DB::table('purchase_order_lines as l')
                ->join('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')
                ->join('clinic_items as ci', 'ci.id', '=', 'l.clinic_item_id')
                ->whereNull('po.deleted_at')
                ->whereBetween('po.order_date', [$from->toDateString(), $to->toDateString()]),
            'po.branch_id'
        )
            ->groupBy('ci.id', 'ci.name')
            ->selectRaw('ci.name as item, SUM(l.qty_ordered) as qty, SUM(l.line_total) as value,
                AVG(l.unit_cost) as avg_cost, COUNT(DISTINCT po.id) as orders')
            ->orderByDesc('value')->limit(15)->get()
            ->map(fn ($r) => [
                'item' => $this->name($r->item),
                'qty' => round((float) $r->qty, 2),
                'value' => round((float) $r->value, 3),
                'avg_cost' => round((float) $r->avg_cost, 3),
                'orders' => (int) $r->orders,
            ])->all();

        // ---- Price drift ----------------------------------------------------------------
        // First vs latest unit cost for anything bought more than once: the early
        // warning that a supplier has been quietly repricing.
        $drift = $scope(
            DB::table('purchase_order_lines as l')
                ->join('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')
                ->join('clinic_items as ci', 'ci.id', '=', 'l.clinic_item_id')
                ->whereNull('po.deleted_at'),
            'po.branch_id'
        )
            ->groupBy('ci.id', 'ci.name')
            ->havingRaw('COUNT(DISTINCT po.id) > 1')
            ->selectRaw('ci.name as item, COUNT(DISTINCT po.id) as orders,
                MIN(l.unit_cost) as min_cost, MAX(l.unit_cost) as max_cost, AVG(l.unit_cost) as avg_cost')
            ->get()
            ->map(function ($r) {
                $min = (float) $r->min_cost;
                $max = (float) $r->max_cost;

                return [
                    'item' => $this->name($r->item),
                    'orders' => (int) $r->orders,
                    'min_cost' => round($min, 3),
                    'max_cost' => round($max, 3),
                    'avg_cost' => round((float) $r->avg_cost, 3),
                    'spread_pct' => $min > 0 ? round((($max - $min) / $min) * 100, 1) : null,
                ];
            })
            ->filter(fn ($r) => ($r['spread_pct'] ?? 0) > 0)
            ->sortByDesc('spread_pct')->take(15)->values()->all();

        // ---- Monthly spend ------------------------------------------------------------------
        $trend = $orders()
            ->groupBy(DB::raw("DATE_FORMAT(po.order_date, '%Y-%m')"))
            ->selectRaw("DATE_FORMAT(po.order_date, '%Y-%m') as m, COUNT(*) as pos, COALESCE(SUM(po.total),0) as value")
            ->orderBy('m')->get()
            ->map(fn ($r) => [
                'label' => Carbon::createFromFormat('Y-m', $r->m)->format('M Y'),
                'pos' => (int) $r->pos,
                'value' => round((float) $r->value, 3),
            ])->all();

        return [
            'kpis' => $kpis,
            'by_vendor' => $vendorRows,
            'pipeline' => $pipeline,
            'ap_aging' => $apAging,
            'top_items' => $topItems,
            'price_drift' => $drift,
            'trend' => $trend,
        ];
    }

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
