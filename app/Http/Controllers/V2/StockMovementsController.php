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

        $query = ClinicStockMovement::query()->with(['clinicItem:id,name', 'branch:id,name']);
        if ($q !== '') {
            $query->whereHas('clinicItem', fn ($i) => $i->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%"));
        }
        if (in_array($type, ['restock', 'consume', 'adjustment'], true)) { $query->where('type', $type); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Date', 'Item', 'Branch', 'Type', 'Qty change', 'After qty', 'Notes'],
                fn ($m) => [$m->id, optional($m->created_at)->format('Y-m-d H:i'), $m->clinicItem?->localized_name, $m->branch?->localized_name, $m->type, $m->qty_change_base, $m->after_qty_base, $m->notes],
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
        ];

        $query = ClinicStockMovement::query()->with(['clinicItem:id,name', 'branch:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('clinicItem', fn ($i) => $i->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%"));
        }
        if (in_array($filters['type'], ['restock', 'consume', 'adjustment'], true)) {
            $query->where('type', $filters['type']);
        }

        $page = $query->orderByDesc('id')->paginate(30)->withQueryString();
        $page->getCollection()->transform(function (ClinicStockMovement $m) {
            $m->setAttribute('item_name', $m->clinicItem?->localized_name);
            $m->setAttribute('branch_name', $m->branch?->localized_name);
            return $m;
        });

        return Inertia::render('StockMovements/Index', [
            'filters' => $filters,
            'page' => $page,
            'types' => ['restock', 'consume', 'adjustment'],
            'counts' => [
                'total' => ClinicStockMovement::query()->count(),
            ],
        ]);
    }
}
