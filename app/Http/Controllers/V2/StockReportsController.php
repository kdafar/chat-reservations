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
 * Stock & Inventory Report — what the clinic is holding, what it burned through,
 * and what needs reordering.
 *
 * The stock screens list movements and on-hand rows but never value them, so
 * nobody could answer "what is our inventory worth" or "which branch is about to
 * run out". This values the holding at item cost, reconciles that total against
 * the inventory control account so a drift is visible rather than silent, and
 * ranks consumption so the buyer knows what actually moves.
 */
class StockReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    /** Inventory control account in the chart of accounts. */
    private const INVENTORY_CODE = '1150';

    public function index(Request $request): Response
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_stock_reports')) {
            abort(403, 'Not authorized to view stock reports.');
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

        $payload = fn () => $this->build($filters, $from, $to, $branchIds);
        $memo = null;
        $get = function (string $key) use (&$memo, $payload) {
            $memo ??= $payload();

            return $memo[$key];
        };

        return Inertia::render('Reports/StockReports', [
            'filters' => $filters,
            'kpis' => Inertia::defer(fn () => $get('kpis'), 'stock'),
            'valuation_by_branch' => Inertia::defer(fn () => $get('valuation_by_branch'), 'stock'),
            'below_reorder' => Inertia::defer(fn () => $get('below_reorder'), 'stock'),
            'top_consumed' => Inertia::defer(fn () => $get('top_consumed'), 'stock'),
            'movement_mix' => Inertia::defer(fn () => $get('movement_mix'), 'stock'),
            'consumption_trend' => Inertia::defer(fn () => $get('consumption_trend'), 'stock'),
            'slow_moving' => Inertia::defer(fn () => $get('slow_moving'), 'stock'),
            'branches' => Branch::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds ?: [0]))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('#'.$b->id)])->all(),
        ]);
    }

    protected function build(array $filters, Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $scopeStock = function ($q) use ($filters, $branchIds) {
            if ($filters['branch_id']) {
                $q->where('clinic_item_stocks.branch_id', $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn('clinic_item_stocks.branch_id', $branchIds ?: [0]);
            }

            return $q;
        };
        $scopeMove = function ($q) use ($filters, $branchIds) {
            if ($filters['branch_id']) {
                $q->where('clinic_stock_movements.branch_id', $filters['branch_id']);
            } elseif ($branchIds !== null) {
                $q->whereIn('clinic_stock_movements.branch_id', $branchIds ?: [0]);
            }

            return $q;
        };

        // ---- Valuation: on-hand × item cost --------------------------------
        $valuationRows = $scopeStock(
            DB::table('clinic_item_stocks')
                ->join('clinic_items', 'clinic_items.id', '=', 'clinic_item_stocks.clinic_item_id')
                ->join('branches', 'branches.id', '=', 'clinic_item_stocks.branch_id')
        )
            ->groupBy('branches.id', 'branches.name')
            ->selectRaw('branches.id as branch_id, branches.name as branch,
                SUM(clinic_item_stocks.qty_on_hand_base * clinic_items.default_cost) as value,
                SUM(clinic_item_stocks.qty_on_hand_base) as units,
                SUM(CASE WHEN clinic_item_stocks.qty_on_hand_base < clinic_item_stocks.min_qty_threshold_base THEN 1 ELSE 0 END) as low_count,
                SUM(CASE WHEN clinic_item_stocks.qty_on_hand_base <= 0 THEN 1 ELSE 0 END) as out_count')
            ->orderByDesc('value')->get();

        $valuationByBranch = $valuationRows->map(fn ($r) => [
            'branch' => $this->name($r->branch),
            'value' => round((float) $r->value, 3),
            'units' => round((float) $r->units, 2),
            'low' => (int) $r->low_count,
            'out' => (int) $r->out_count,
        ])->all();

        $totalValue = round((float) $valuationRows->sum('value'), 3);

        // The inventory control account, for comparison. A gap means movements
        // were valued at a cost that has since changed — worth seeing, not hiding.
        $glInventory = round((float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->where('chart_of_accounts.code', self::INVENTORY_CODE)
            ->sum(DB::raw('journal_entry_lines.debit - journal_entry_lines.credit')), 3);

        // ---- Consumption in the window -------------------------------------
        $consumedValue = round((float) $scopeMove(
            DB::table('clinic_stock_movements')
                ->join('clinic_items', 'clinic_items.id', '=', 'clinic_stock_movements.clinic_item_id')
        )
            ->where('clinic_stock_movements.type', 'consume')
            ->whereBetween('clinic_stock_movements.created_at', [$from, $to])
            ->sum(DB::raw('ABS(clinic_stock_movements.qty_change_base) * clinic_items.default_cost')), 3);

        // Stock turn, annualised from the window: cost consumed ÷ average holding.
        $days = max(1, $from->diffInDays($to) + 1);
        $turn = $totalValue > 0 ? round(($consumedValue / $totalValue) * (365 / $days), 2) : 0;

        $kpis = [
            'total_value' => $totalValue,
            'gl_inventory' => $glInventory,
            'variance' => round($totalValue - $glInventory, 3),
            'consumed_value' => $consumedValue,
            'turn' => $turn,
            'low_count' => (int) $valuationRows->sum('low_count'),
            'out_count' => (int) $valuationRows->sum('out_count'),
            'sku_count' => (int) $scopeStock(DB::table('clinic_item_stocks'))->distinct()->count('clinic_item_stocks.clinic_item_id'),
        ];

        // ---- Below reorder point -------------------------------------------
        $belowReorder = $scopeStock(
            DB::table('clinic_item_stocks')
                ->join('clinic_items', 'clinic_items.id', '=', 'clinic_item_stocks.clinic_item_id')
                ->join('branches', 'branches.id', '=', 'clinic_item_stocks.branch_id')
        )
            ->whereColumn('clinic_item_stocks.qty_on_hand_base', '<', 'clinic_item_stocks.min_qty_threshold_base')
            ->selectRaw('clinic_items.name as item, branches.name as branch, clinic_items.usage_unit as unit,
                clinic_item_stocks.qty_on_hand_base as on_hand,
                clinic_item_stocks.min_qty_threshold_base as threshold,
                (clinic_item_stocks.min_qty_threshold_base - clinic_item_stocks.qty_on_hand_base) as shortfall,
                clinic_items.default_cost as cost')
            ->orderByDesc('shortfall')->limit(60)->get()
            ->map(fn ($r) => [
                'item' => $this->name($r->item),
                'branch' => $this->name($r->branch),
                'unit' => (string) ($r->unit ?: ''),
                'on_hand' => round((float) $r->on_hand, 2),
                'threshold' => round((float) $r->threshold, 2),
                'shortfall' => round((float) $r->shortfall, 2),
                'reorder_value' => round((float) $r->shortfall * (float) $r->cost, 3),
            ])->all();

        // ---- What actually gets used ---------------------------------------
        $topConsumed = $scopeMove(
            DB::table('clinic_stock_movements')
                ->join('clinic_items', 'clinic_items.id', '=', 'clinic_stock_movements.clinic_item_id')
        )
            ->where('clinic_stock_movements.type', 'consume')
            ->whereBetween('clinic_stock_movements.created_at', [$from, $to])
            ->groupBy('clinic_items.id', 'clinic_items.name', 'clinic_items.usage_unit')
            ->selectRaw('clinic_items.name as item, clinic_items.usage_unit as unit,
                SUM(ABS(clinic_stock_movements.qty_change_base)) as qty,
                SUM(ABS(clinic_stock_movements.qty_change_base) * clinic_items.default_cost) as value,
                COUNT(*) as movements')
            ->orderByDesc('value')->limit(15)->get()
            ->map(fn ($r) => [
                'item' => $this->name($r->item),
                'unit' => (string) ($r->unit ?: ''),
                'qty' => round((float) $r->qty, 2),
                'value' => round((float) $r->value, 3),
                'movements' => (int) $r->movements,
            ])->all();

        // ---- Movement mix ----------------------------------------------------
        $movementMix = $scopeMove(
            DB::table('clinic_stock_movements')
                ->join('clinic_items', 'clinic_items.id', '=', 'clinic_stock_movements.clinic_item_id')
        )
            ->whereBetween('clinic_stock_movements.created_at', [$from, $to])
            ->groupBy('clinic_stock_movements.type')
            ->selectRaw('clinic_stock_movements.type as type, COUNT(*) as c,
                SUM(ABS(clinic_stock_movements.qty_change_base) * clinic_items.default_cost) as value')
            ->orderByDesc('value')->get()
            ->map(fn ($r) => ['type' => (string) $r->type, 'count' => (int) $r->c, 'value' => round((float) $r->value, 3)])->all();

        // ---- Consumption trend ------------------------------------------------
        $trend = $scopeMove(
            DB::table('clinic_stock_movements')
                ->join('clinic_items', 'clinic_items.id', '=', 'clinic_stock_movements.clinic_item_id')
        )
            ->where('clinic_stock_movements.type', 'consume')
            ->whereBetween('clinic_stock_movements.created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(clinic_stock_movements.created_at)'))
            ->selectRaw('DATE(clinic_stock_movements.created_at) as d,
                SUM(ABS(clinic_stock_movements.qty_change_base) * clinic_items.default_cost) as value')
            ->orderBy('d')->get()
            ->map(fn ($r) => ['date' => Carbon::parse($r->d)->format('d M'), 'value' => round((float) $r->value, 3)])->all();

        // ---- Capital sitting still --------------------------------------------
        // Held stock with no consumption in 90 days: the money the clinic has
        // tied up in things it isn't using.
        $movedIds = $scopeMove(DB::table('clinic_stock_movements'))
            ->where('clinic_stock_movements.type', 'consume')
            ->where('clinic_stock_movements.created_at', '>=', Carbon::now()->subDays(90))
            ->distinct()->pluck('clinic_stock_movements.clinic_item_id')->all();

        $slowMoving = $scopeStock(
            DB::table('clinic_item_stocks')
                ->join('clinic_items', 'clinic_items.id', '=', 'clinic_item_stocks.clinic_item_id')
                ->join('branches', 'branches.id', '=', 'clinic_item_stocks.branch_id')
        )
            ->where('clinic_item_stocks.qty_on_hand_base', '>', 0)
            ->when($movedIds, fn ($q) => $q->whereNotIn('clinic_item_stocks.clinic_item_id', $movedIds))
            ->selectRaw('clinic_items.name as item, branches.name as branch,
                clinic_item_stocks.qty_on_hand_base as on_hand,
                (clinic_item_stocks.qty_on_hand_base * clinic_items.default_cost) as value')
            ->orderByDesc('value')->limit(20)->get()
            ->map(fn ($r) => [
                'item' => $this->name($r->item),
                'branch' => $this->name($r->branch),
                'on_hand' => round((float) $r->on_hand, 2),
                'value' => round((float) $r->value, 3),
            ])->all();

        return [
            'kpis' => $kpis,
            'valuation_by_branch' => $valuationByBranch,
            'below_reorder' => $belowReorder,
            'top_consumed' => $topConsumed,
            'movement_mix' => $movementMix,
            'consumption_trend' => $trend,
            'slow_moving' => $slowMoving,
        ];
    }

    /** Item and branch names are stored as {en,ar} JSON blobs. */
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
