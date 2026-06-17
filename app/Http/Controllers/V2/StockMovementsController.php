<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicStockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock Movements — v2 replacement for Filament ClinicStockMovementResource.
 * Read-only audit log; rows are created only by ClinicStockService. Branch-scoped.
 */
class StockMovementsController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_clinic_stock_movement')) {
            abort(403, 'Not authorized to view stock movements.');
        }
    }

    /** Styled .xlsx export of stock movements (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $type = $request->input('type', 'all');
        $itemId = $request->filled('item_id') ? (int) $request->input('item_id') : null;
        $performedBy = $request->filled('performed_by') ? (int) $request->input('performed_by') : null;
        $from = $request->filled('from') ? (string) $request->input('from') : null;
        $to = $request->filled('to') ? (string) $request->input('to') : null;

        $query = ClinicStockMovement::query()->with(['clinicItem:id,name', 'branch:id,name', 'performedBy:id,name']);
        if ($q !== '') {
            $query->whereHas('clinicItem', fn ($i) => $i->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%"));
        }
        if ($itemId) { $query->where('clinic_item_id', $itemId); }
        if ($performedBy) { $query->where('performed_by', $performedBy); }
        if (in_array($type, ['restock', 'consume', 'adjustment'], true)) { $query->where('type', $type); }
        if ($from) { $query->whereDate('created_at', '>=', $from); }
        if ($to) { $query->whereDate('created_at', '<=', $to); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Date', 'Item', 'Branch', 'Type', 'Qty change', 'After qty', 'By', 'Notes'],
                fn ($m) => [$m->id, optional($m->created_at)->format('Y-m-d H:i'), $m->clinicItem?->localized_name, $m->branch?->localized_name, $m->type, $m->qty_change_base, $m->after_qty_base, $m->performedBy?->name ?: 'System', $m->notes],
                'Stock Movements',
                app()->getLocale() === 'ar',
            ),
            'stock-movements-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => $request->input('type', 'all'),
            'item_id' => $request->filled('item_id') ? (int) $request->input('item_id') : null,
            'performed_by' => $request->filled('performed_by') ? (int) $request->input('performed_by') : null,
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
        ];

        $query = ClinicStockMovement::query()->with(['clinicItem:id,name', 'branch:id,name', 'performedBy:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('clinicItem', fn ($i) => $i->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%"));
        }
        if ($filters['item_id']) {
            $query->where('clinic_item_id', $filters['item_id']);
        }
        if ($filters['performed_by']) {
            $query->where('performed_by', $filters['performed_by']);
        }
        if (in_array($filters['type'], ['restock', 'consume', 'adjustment'], true)) {
            $query->where('type', $filters['type']);
        }
        if ($filters['from']) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to']) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $page = $query->orderByDesc('id')->paginate(30)->withQueryString();
        $page->getCollection()->transform(function (ClinicStockMovement $m) {
            $m->setAttribute('item_name', $m->clinicItem?->localized_name);
            $m->setAttribute('branch_name', $m->branch?->localized_name);
            $m->setAttribute('performed_by_name', $m->performedBy?->name);
            return $m;
        });

        // Item filter options — only items that actually appear in the (branch-
        // scoped) movement log, so the list stays short and relevant. Each
        // movement points at one specific item row, so the distinct set has no
        // cross-clinic duplicates even for a global admin.
        $itemIds = ClinicStockMovement::query()->distinct()->pluck('clinic_item_id')->filter()->all();
        $items = \App\Models\ClinicItem::query()->withoutGlobalScopes()
            ->whereIn('id', $itemIds)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($i) => ['id' => $i->id, 'name' => $i->localized_name])->all();

        // User filter options — only staff who actually performed a (scoped) movement.
        $userIds = ClinicStockMovement::query()->whereNotNull('performed_by')->distinct()->pluck('performed_by')->all();
        $users = \App\Models\User::query()->whereIn('id', $userIds)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();

        return Inertia::render('StockMovements/Index', [
            'filters' => $filters,
            'page' => $page,
            'types' => ['restock', 'consume', 'adjustment'],
            'items' => $items,
            'users' => $users,
            'counts' => [
                'total' => ClinicStockMovement::query()->count(),
            ],
        ]);
    }
}
