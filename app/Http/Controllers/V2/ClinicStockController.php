<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Services\Clinic\ClinicStockService;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clinic Stock — v2 replacement for Filament ClinicItemStockResource.
 * qty_on_hand_base is never edited directly — it moves only through
 * ClinicStockService::restock() (the "Receive stock" action), which writes an
 * auditable ClinicStockMovement. Branch-scoped via the model global scope.
 */
class ClinicStockController extends Controller
{
    use ResolvesAccessibleClinics;

    public function __construct(protected ClinicStockService $stock) {}

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_clinic_item_stocks')) {
            abort(403, 'Not authorized to view stock.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_clinic_item_stocks');
    }

    /** Styled .xlsx export of clinic stock-on-hand (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $low = (bool) $request->boolean('low');

        $query = ClinicItemStock::query()->with(['clinicItem:id,name', 'branch:id,name']);
        if ($q !== '') {
            $query->whereHas('clinicItem', fn ($i) => $i->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%"));
        }
        if ($low) { $query->whereNotNull('min_qty_threshold_base')->whereColumn('qty_on_hand_base', '<=', 'min_qty_threshold_base'); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('id'),
                ['ID', 'Item', 'Branch', 'On hand', 'Min threshold', 'Bin', 'Low stock'],
                fn ($s) => [$s->id, $s->clinicItem?->localized_name, $s->branch?->localized_name, $s->qty_on_hand_base, $s->min_qty_threshold_base, $s->bin_location, $isLow ? 'Yes' : 'No'],
                'Clinic Stock',
                app()->getLocale() === 'ar',
            ),
            'clinic-stock-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'low' => (bool) $request->boolean('low'),
        ];

        $query = ClinicItemStock::query()->with(['clinicItem:id,name', 'branch:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('clinicItem', fn ($i) => $i->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%"));
        }
        if ($filters['low']) {
            $query->whereNotNull('min_qty_threshold_base')
                ->whereColumn('qty_on_hand_base', '<=', 'min_qty_threshold_base');
        }

        // Server-side summary over the FULL filtered set (not just the current
        // page). Total on-hand quantity, plus total on-hand value using each
        // item's default_cost (cost per base unit). Clone before paginate so the
        // aggregate query is unaffected by the limit/offset.
        $summaryQuery = (clone $query);
        $totalQty = (float) $summaryQuery->sum('qty_on_hand_base');
        $totalValue = (float) (clone $query)
            ->join('clinic_items', 'clinic_items.id', '=', 'clinic_item_stocks.clinic_item_id')
            ->sum(DB::raw('clinic_item_stocks.qty_on_hand_base * clinic_items.default_cost'));
        $summary = [
            'total_qty' => $totalQty,
            'total_value' => $totalValue,
            'has_value' => true,
        ];

        $page = $query->orderBy('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (ClinicItemStock $s) {
            $s->setAttribute('item_name', $s->clinicItem?->localized_name);
            $s->setAttribute('branch_name', $s->branch?->localized_name);
            $s->setAttribute('is_low', $s->min_qty_threshold_base !== null
                && (float) $s->qty_on_hand_base <= (float) $s->min_qty_threshold_base);
            return $s;
        });

        return Inertia::render('ClinicStock/Index', [
            'filters' => $filters,
            'page' => $page,
            'summary' => $summary,
            'branches' => $this->branchOptions(),
            'items' => $this->itemOptions(),
            'counts' => [
                'total' => ClinicItemStock::query()->count(),
                'low' => ClinicItemStock::query()->whereNotNull('min_qty_threshold_base')
                    ->whereColumn('qty_on_hand_base', '<=', 'min_qty_threshold_base')->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('create_clinic_item_stocks')) abort(403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'clinic_item_id' => [
                'required', 'integer', 'exists:clinic_items,id',
                Rule::unique('clinic_item_stocks')->where(fn ($q) => $q->where('branch_id', $request->input('branch_id'))),
            ],
            'min_qty_threshold_base' => ['nullable', 'numeric', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:191'],
        ]);

        $item = ClinicItem::query()->findOrFail($data['clinic_item_id']);
        if (! $this->itemFitsBranch($item, (int) $data['branch_id'])) {
            return back()->withErrors(['clinic_item_id' => "That item isn't eligible for stock at the selected branch."]);
        }

        ClinicItemStock::create($data + ['qty_on_hand_base' => 0]);
        return back()->with('flash', ['type' => 'success', 'message' => 'Stock record created.']);
    }

    public function update(Request $request, ClinicItemStock $stock): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);

        // Only the threshold + bin are editable; qty moves via stock movements.
        $data = $request->validate([
            'min_qty_threshold_base' => ['nullable', 'numeric', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:191'],
        ]);
        $stock->update($data);
        return back()->with('flash', ['type' => 'success', 'message' => 'Stock record updated.']);
    }

    public function destroy(Request $request, ClinicItemStock $stock): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('delete_clinic_item_stocks')) abort(403);
        $stock->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Stock record removed.']);
    }

    /** Receive stock — restock through the service (creates an audited movement). */
    public function receive(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'clinic_item_id' => ['required', 'integer', 'exists:clinic_items,id'],
            'qty_stock_units' => ['nullable', 'numeric', 'min:0'],
            'qty_base' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:191'],
        ]);

        $qtyStockUnits = ($data['qty_stock_units'] ?? 0) > 0 ? (float) $data['qty_stock_units'] : null;
        $qtyBase = ($data['qty_base'] ?? 0) > 0 ? (float) $data['qty_base'] : null;
        if ($qtyStockUnits === null && $qtyBase === null) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Enter a quantity (stock units or base units).']);
        }

        $item = ClinicItem::query()->findOrFail($data['clinic_item_id']);
        if (! $this->itemFitsBranch($item, (int) $data['branch_id'])) {
            return back()->with('flash', ['type' => 'error', 'message' => "That item isn't eligible for stock at the selected branch."]);
        }
        try {
            $this->stock->restock(
                branchId: (int) $data['branch_id'],
                item: $item,
                qtyStockUnits: $qtyStockUnits,
                qtyBase: $qtyBase,
                performedBy: (int) $request->user()->id,
                notes: $data['notes'] ?? null,
                related: null,
            );
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Stock received.']);
    }

    protected function branchOptions(): array
    {
        return $this->accessibleBranches()->all();
    }

    /**
     * Whether a clinic item may receive / hold stock at a branch. It must be:
     *   - active and stockable (you can't stock a service or a retired item), and
     *   - global, or belonging to that branch's clinic, and not pinned to a
     *     different branch.
     * The picker already enforces this in the UI; this is the server-side guard
     * against a stale tab or a crafted request hitting the wrong/ineligible row.
     */
    protected function itemFitsBranch(ClinicItem $item, int $branchId): bool
    {
        if (! $item->is_active || ! $item->is_stockable) {
            return false;
        }
        $branch = \App\Models\Branch::query()->find($branchId);
        if (! $branch) {
            return false;
        }
        if ($item->partner_id !== null && (int) $item->partner_id !== (int) $branch->partner_id) {
            return false;
        }
        if ($item->branch_id !== null && (int) $item->branch_id !== $branchId) {
            return false;
        }

        return true;
    }

    protected function itemOptions(): array
    {
        $partnerIds = $this->accessiblePartnerIds(); // null = unrestricted (super admin sees all clinics)

        return ClinicItem::query()
            ->where('is_active', true)
            // You can only receive / track stock for stockable items — services
            // and pure billables were polluting the picker (and are the bulk of
            // the same-name "duplicates": one catalog row per clinic).
            ->where('is_stockable', true)
            ->when($partnerIds !== null, fn ($w) => $w->where(function ($w2) use ($partnerIds) {
                $w2->whereIn('partner_id', $partnerIds)->orWhereNull('partner_id');
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'partner_id', 'branch_id', 'conversion_factor', 'consume_step'])
            // partner_id/branch_id let the modal narrow items to the chosen
            // branch's clinic (so a super-admin no longer sees one row per clinic);
            // conversion_factor drives base-unit prepopulation.
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->localized_name,
                'partner_id' => $i->partner_id,
                'branch_id' => $i->branch_id,
                'conversion_factor' => (float) ($i->conversion_factor ?? 0),
                'consume_step' => (float) ($i->consume_step ?? 0),
            ])->all();
    }
}
