<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\ClinicItem;
use App\Services\Accounting\ChartOfAccounts;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clinic Items (Pharmacy) — v2 replacement for Filament ClinicItemResource.
 * `name` is a JSON {en, ar}. Consumable items can be stockable (extra inventory
 * fields); service items never are.
 */
class ClinicItemsController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_clinic_items')) {
            abort(403, 'Not authorized to view clinic items.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_clinic_items');
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $type = $request->input('type', 'all');
        $active = $request->input('active', 'all');
        $query = ClinicItem::query()->with('branch:id,name');
        if ($q !== '') { $query->where(fn ($w) => $w->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%")); }
        if (in_array($type, ['consumable', 'service', 'product'], true)) { $query->where('type', $type); }
        if ($active === 'active') { $query->where('is_active', true); } elseif ($active === 'inactive') { $query->where('is_active', false); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('id'),
                ['ID', 'Name (EN)', 'Name (AR)', 'Type', 'Branch', 'Cost', 'Price', 'Billable', 'Stockable', 'Active'],
                fn ($it) => [$it->id, $n['en'] ?? null, $n['ar'] ?? null, $it->type, $it->branch?->localized_name, number_format((float) $it->default_cost, 3, '.', ''), number_format((float) $it->default_price, 3, '.', ''), $it->is_billable ? 'Yes' : 'No', $it->is_stockable ? 'Yes' : 'No', $it->is_active ? 'Yes' : 'No'],
                'Clinic Items',
                app()->getLocale() === 'ar',
            ),
            'clinic-items-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $locale = app()->getLocale();
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => $request->input('type', 'all'),
            'active' => $request->input('active', 'all'),
        ];

        $query = ClinicItem::query()->with(['branch:id,name', 'components']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('name->en', 'like', "%{$q}%")->orWhere('name->ar', 'like', "%{$q}%");
            });
        }
        if (in_array($filters['type'], ['consumable', 'service', 'product'], true)) {
            $query->where('type', $filters['type']);
        }
        if ($filters['active'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['active'] === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (ClinicItem $it) => $this->presentItem($it, $locale));

        // Deep-link: ?open={id} (e.g. from a low-stock notification) opens that
        // item's editor on load, even when the row isn't on the current page.
        $openRecord = null;
        if ($openId = $request->integer('open')) {
            $it = ClinicItem::query()->with(['branch:id,name', 'components'])->find($openId);
            if ($it) {
                $openRecord = $this->presentItem($it, $locale);
            }
        }

        return Inertia::render('ClinicItems/Index', [
            'filters' => $filters,
            'page' => $page,
            'open_record' => $openRecord,
            'branches' => $this->branchOptions(),
            'componentItems' => $this->componentItemOptions(),
            'types' => ['consumable', 'service', 'product'],
            'counts' => [
                'total' => ClinicItem::query()->count(),
                'active' => ClinicItem::query()->where('is_active', true)->count(),
            ],
            'can_edit' => $this->canEdit($request),
            'can_edit_accounting' => (bool) $request->user()?->can('update_accounting_accounts'),
            'inventoryAccounts' => Account::postableOptions([Account::TYPE_ASSET]),
            'cogsAccounts' => Account::postableOptions([Account::TYPE_COGS, Account::TYPE_EXPENSE]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $data = $this->validated($request);
        $data['partner_id'] = $this->defaultPartnerId($data['branch_id'] ?? null); // clinic-owned
        $item = ClinicItem::create($data);
        $this->syncComponents($request, $item);
        $this->refreshAccountingIfPermitted($request);
        return back()->with('flash', ['type' => 'success', 'message' => 'Item added.']);
    }

    public function update(Request $request, ClinicItem $clinicItem): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $clinicItem->update($this->validated($request));
        $this->syncComponents($request, $clinicItem);
        $this->refreshAccountingIfPermitted($request);
        return back()->with('flash', ['type' => 'success', 'message' => 'Item updated.']);
    }

    /** Drop the ChartOfAccounts cache so an inventory/COGS-account change applies at once. */
    protected function refreshAccountingIfPermitted(Request $request): void
    {
        if ($request->user()?->can('update_accounting_accounts')) {
            app(ChartOfAccounts::class)->refresh();
        }
    }

    public function destroy(Request $request, ClinicItem $clinicItem): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        try {
            $clinicItem->delete();
        } catch (QueryException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Cannot delete — this item has stock or usage history. Mark it inactive instead.']);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Item deleted.']);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'type' => ['required', 'in:consumable,service,product'],
            'name_en' => ['required', 'string', 'max:191'],
            'name_ar' => ['required', 'string', 'max:191'],
            'is_active' => ['sometimes', 'boolean'],
            'is_stockable' => ['sometimes', 'boolean'],
            'stock_unit' => ['nullable', 'string', 'max:50'],
            'usage_unit' => ['nullable', 'string', 'max:50'],
            'conversion_factor' => ['nullable', 'numeric', 'min:0.0001'],
            'consume_step' => ['nullable', 'numeric', 'min:0.0001'],
            'is_billable' => ['sometimes', 'boolean'],
            'default_cost' => ['required', 'numeric', 'min:0'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'inventory_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'cogs_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $isService = $data['type'] === 'service';
        $stockable = ! $isService && (bool) $request->input('is_stockable', false);

        if ($stockable) {
            $request->validate([
                'stock_unit' => ['required', 'string', 'max:50'],
                'usage_unit' => ['required', 'string', 'max:50'],
                'conversion_factor' => ['required', 'numeric', 'min:0.0001'],
            ]);
        }

        $out = [
            'branch_id' => $data['branch_id'] ?? null,
            'type' => $data['type'],
            'name' => ['en' => $data['name_en'], 'ar' => $data['name_ar']],
            'is_active' => (bool) $request->input('is_active', true),
            'is_stockable' => $stockable,
            'stock_unit' => $stockable ? $data['stock_unit'] : null,
            'usage_unit' => $stockable ? $data['usage_unit'] : null,
            'conversion_factor' => $stockable ? $data['conversion_factor'] : null,
            'consume_step' => $stockable ? ($data['consume_step'] ?? 1) : 1,
            'is_billable' => $isService ? true : (bool) $request->input('is_billable', true),
            'default_cost' => $data['default_cost'],
            'default_price' => $data['default_price'],
        ];

        // Only an accountant may set the inventory/COGS account links; others leave them as-is.
        if ($request->user()?->can('update_accounting_accounts')) {
            $out['inventory_account_id'] = $data['inventory_account_id'] ?? null;
            $out['cogs_account_id'] = $data['cogs_account_id'] ?? null;
        }

        return $out;
    }

    protected function branchOptions(): array
    {
        return $this->accessibleBranches()->all();
    }

    /** Add display fields + bom_lines to an item for the v2 page/editor. */
    protected function presentItem(ClinicItem $it, string $locale): ClinicItem
    {
        $name = is_array($it->name) ? $it->name : [];
        $it->setAttribute('display_name', $name[$locale] ?? $name['en'] ?? $name['ar'] ?? ('#'.$it->id));
        $it->setAttribute('branch_name', $it->branch?->localized_name);
        $it->setAttribute('bom_lines', $it->relationLoaded('components')
            ? $it->components->map(fn (\App\Models\ClinicItemComponent $c) => [
                'component_item_id' => (int) $c->component_item_id,
                'qty_base' => (float) $c->qty_base,
                'is_optional' => (bool) $c->is_optional,
            ])->values()->all()
            : []);
        $it->makeHidden('components'); // expose only the flat bom_lines payload

        return $it;
    }

    /** Items eligible as BOM components: active, stock-bearing (not services). */
    protected function componentItemOptions(): array
    {
        $ar = app()->getLocale() === 'ar';
        $typeLabel = fn (string $t) => match ($t) {
            'consumable' => $ar ? 'مستهلك' : 'Consumable',
            'product' => $ar ? 'منتج' : 'Product',
            default => $t,
        };

        return ClinicItem::query()
            ->where('is_active', 1)
            ->where('type', '!=', 'service')
            ->get(['id', 'name', 'type'])
            ->sortBy(fn (ClinicItem $it) => $it->localized_name)
            ->values()
            ->map(fn (ClinicItem $it) => [
                'id' => $it->id,
                'name' => $it->localized_name,
                'sublabel' => $typeLabel($it->type),
            ])->all();
    }

    /**
     * Replace a service's bill of materials. Non-services never carry one, so
     * their components are cleared.
     */
    protected function syncComponents(Request $request, ClinicItem $item): void
    {
        if ($item->type !== 'service') {
            $item->components()->delete();

            return;
        }

        $rows = $request->validate([
            'components' => ['array'],
            'components.*.component_item_id' => ['required', 'integer', 'exists:clinic_items,id'],
            'components.*.qty_base' => ['required', 'numeric', 'min:0.0001'],
            'components.*.is_optional' => ['sometimes', 'boolean'],
        ])['components'] ?? [];

        // Components may only be stock-bearing items (consumable/product), never
        // services — a service-as-component wouldn't explode and would be treated
        // as stock. Guards against crafted payloads (the UI already excludes them).
        $ids = collect($rows)->pluck('component_item_id')->map(fn ($v) => (int) $v)->unique();
        $allowed = ClinicItem::query()->whereIn('id', $ids)->where('type', '!=', 'service')->pluck('id')->all();
        $allowed = array_flip($allowed);

        $item->components()->delete();

        $seen = [];
        foreach ($rows as $row) {
            $componentId = (int) $row['component_item_id'];
            // Skip: self, duplicates, and anything that isn't a stock item.
            if ($componentId === (int) $item->id || isset($seen[$componentId]) || ! isset($allowed[$componentId])) {
                continue;
            }
            $seen[$componentId] = true;

            $item->components()->create([
                'component_item_id' => $componentId,
                'qty_base' => (float) $row['qty_base'],
                'is_optional' => (bool) ($row['is_optional'] ?? false),
            ]);
        }
    }
}
