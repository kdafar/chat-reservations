<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clinic Packages — v2 replacement for the Filament ClinicPackageResource.
 *
 * A bundle (name + price) of clinic items that a doctor can add to a visit in
 * one tap. branch_id null = available at every branch. Editing the bundle
 * resyncs its line items.
 */
class ClinicPackagesController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeView(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_clinic_packages')) {
            abort(403, 'Not authorized to view packages.');
        }
    }

    protected function authorizeWrite(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('create_clinic_packages')) {
            abort(403, 'Not authorized to manage packages.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeView($request);
        $q = trim((string) $request->input('q', ''));
        $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;
        $status = $request->input('status', 'all');
        $query = ClinicPackage::query()->with('branch:id,name')->withCount('items');
        if ($q !== '') { $query->where(fn ($w) => $w->where('name->en', 'like', "%{$q}%")); }
        if ($branchId) { $query->where('branch_id', $branchId); }
        if ($status === 'active') { $query->where('is_active', true); } elseif ($status === 'inactive') { $query->where('is_active', false); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('id'),
                ['ID', 'Name (EN)', 'Name (AR)', 'Branch', 'Price', 'Items', 'Active'],
                fn ($p) => [$p->id, $n['en'] ?? null, $n['ar'] ?? null, $p->branch?->localized_name, number_format((float) $p->default_price, 3, '.', ''), $p->items_count, $p->is_active ? 'Yes' : 'No'],
                'Clinic Packages',
                app()->getLocale() === 'ar',
            ),
            'clinic-packages-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $locale = app()->getLocale();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'branch_id' => $request->input('branch_id') ? (int) $request->input('branch_id') : null,
            'status' => $request->input('status', 'all'),
        ];

        $query = ClinicPackage::query()->with(['branch:id,name', 'items.clinicItem'])->withCount('items');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q, $locale) {
                $qq->where("name->{$locale}", 'like', "%{$q}%")->orWhere('name->en', 'like', "%{$q}%");
            });
        }
        if ($filters['branch_id']) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (ClinicPackage $p) => $this->present($p, $locale));

        return Inertia::render('ClinicPackages/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $this->accessibleBranches()->all(),
            'clinicItems' => $this->clinicItemOptions(),
            'counts' => [
                'total' => ClinicPackage::query()->count(),
                'active' => ClinicPackage::query()->where('is_active', true)->count(),
            ],
            'can_manage' => (bool) $request->user()?->can('create_clinic_packages'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $package = ClinicPackage::create([
                'partner_id' => $this->defaultPartnerId($data['branch_id'] ?? null), // clinic-owned
                'branch_id' => $data['branch_id'] ?? null,
                'name' => ['en' => $data['name_en'], 'ar' => $data['name_ar']],
                'default_price' => $data['default_price'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->syncItems($package, $data['items'] ?? []);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Package created.']);
    }

    public function update(Request $request, ClinicPackage $clinicPackage): RedirectResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validateData($request);

        DB::transaction(function () use ($clinicPackage, $data) {
            $clinicPackage->update([
                'branch_id' => $data['branch_id'] ?? null,
                'name' => ['en' => $data['name_en'], 'ar' => $data['name_ar']],
                'default_price' => $data['default_price'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->syncItems($clinicPackage, $data['items'] ?? []);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Package updated.']);
    }

    public function destroy(Request $request, ClinicPackage $clinicPackage): RedirectResponse
    {
        $this->authorizeWrite($request);

        DB::transaction(function () use ($clinicPackage) {
            $clinicPackage->items()->delete();
            $clinicPackage->delete();
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Package deleted.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name_en' => ['required', 'string', 'max:191'],
            'name_ar' => ['required', 'string', 'max:191'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'items' => ['array'],
            'items.*.clinic_item_id' => ['required', 'integer', 'exists:clinic_items,id'],
            'items.*.qty_base' => ['required', 'numeric', 'min:0.0001'],
            'items.*.is_consumable' => ['boolean'],
        ]);
    }

    protected function syncItems(ClinicPackage $package, array $items): void
    {
        $package->items()->delete();
        foreach ($items as $row) {
            $package->items()->create([
                'clinic_item_id' => $row['clinic_item_id'],
                'qty_base' => $row['qty_base'],
                'is_consumable' => $row['is_consumable'] ?? true,
            ]);
        }
    }

    /**
     * Item-picker options for the package builder. A package line can be ANY
     * clinic item — service, consumable or product — so the type is surfaced as
     * a sublabel and services are floated to the top so they are easy to find
     * (they tend to seed with low ids and would otherwise sink to the bottom).
     */
    protected function clinicItemOptions(): array
    {
        $priority = ['service' => 0, 'product' => 1, 'consumable' => 2];

        return ClinicItem::query()->where('is_active', 1)->get(['id', 'name', 'type'])
            ->sortBy(fn (ClinicItem $it) => sprintf('%d|%s', $priority[$it->type] ?? 9, $it->localized_name))
            ->values()
            ->map(fn (ClinicItem $it) => [
                'id' => $it->id,
                'name' => $it->localized_name,
                'sublabel' => $this->itemTypeLabel($it->type),
            ])->all();
    }

    protected function itemTypeLabel(string $type): string
    {
        $ar = app()->getLocale() === 'ar';

        return match ($type) {
            'service' => $ar ? 'خدمة' : 'Service',
            'product' => $ar ? 'منتج' : 'Product',
            'consumable' => $ar ? 'مستهلك' : 'Consumable',
            default => $type,
        };
    }

    protected function present(ClinicPackage $p, string $locale): array
    {
        return [
            'id' => $p->id,
            'name' => $p->localized_name,
            'name_en' => $p->name['en'] ?? '',
            'name_ar' => $p->name['ar'] ?? '',
            'branch_id' => $p->branch_id,
            'branch_name' => $p->branch ? $p->branch->localized_name : null,
            'default_price' => (float) $p->default_price,
            'is_active' => (bool) $p->is_active,
            'items_count' => $p->items_count,
            'items' => $p->items->map(fn ($it) => [
                'clinic_item_id' => $it->clinic_item_id,
                'name' => $it->clinicItem?->localized_name ?? ('#'.$it->clinic_item_id),
                'qty_base' => (float) $it->qty_base,
                'is_consumable' => (bool) $it->is_consumable,
            ])->all(),
        ];
    }
}
