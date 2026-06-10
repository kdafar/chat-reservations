<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\ClinicPromotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Time-bound catalog promotions — auto-discount items/services/packages on a
 * visit. A promotion targets: all items, a type, one item, a hand-picked set
 * of items, all packages, or a hand-picked set of packages. Admin-managed.
 */
class ClinicPromotionsController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorize(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(['admin', 'super_admin', 'clinic_admin', 'clinic_reception'])) {
            abort(403, 'Not authorized to manage promotions.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorize($request);

        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = ClinicPromotion::query()->with(['branch:id,name', 'clinicItem:id,name', 'items:id,name', 'packages:id,name']);
        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderByDesc('priority')->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (ClinicPromotion $p) => $this->present($p));

        return Inertia::render('Promotions/Index', [
            'filters' => ['q' => $q, 'status' => $status],
            'page' => $page,
            'branches' => Branch::query()->when($this->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('id', $this->accessibleBranchIds() ?: [0]))->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all(),
            'clinicItems' => ClinicItem::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'type'])
                ->map(fn ($it) => ['id' => $it->id, 'name' => $it->localized_name, 'sublabel' => ucfirst($it->type)])->all(),
            'clinicPackages' => ClinicPackage::query()->where('is_active', 1)->orderByDesc('id')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->localized_name])->all(),
            'counts' => [
                'total' => ClinicPromotion::query()->count(),
                'active' => ClinicPromotion::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $data = $this->validated($request);
        $promo = ClinicPromotion::create($this->attrs($data));
        $this->syncTargets($promo, $data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Promotion created.']);
    }

    public function update(Request $request, ClinicPromotion $clinicPromotion): RedirectResponse
    {
        $this->authorize($request);
        $data = $this->validated($request);
        $clinicPromotion->update($this->attrs($data));
        $this->syncTargets($clinicPromotion, $data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Promotion updated.']);
    }

    public function destroy(Request $request, ClinicPromotion $clinicPromotion): RedirectResponse
    {
        $this->authorize($request);
        $clinicPromotion->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Promotion deleted.']);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'discount_type' => ['required', 'in:amount,percent'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'scope' => ['required', 'in:all,type,item,items,all_packages,packages'],
            'clinic_item_id' => ['nullable', 'integer', 'exists:clinic_items,id', 'required_if:scope,item'],
            'item_type' => ['nullable', 'in:service,consumable,product', 'required_if:scope,type'],
            'item_ids' => ['array', 'required_if:scope,items'],
            'item_ids.*' => ['integer', 'exists:clinic_items,id'],
            'package_ids' => ['array', 'required_if:scope,packages'],
            'package_ids.*' => ['integer', 'exists:clinic_packages,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /** Columns on clinic_promotions (pivots handled separately). */
    protected function attrs(array $data): array
    {
        return [
            'name' => $data['name'],
            'discount_type' => $data['discount_type'],
            'discount_value' => (float) $data['discount_value'],
            'scope' => $data['scope'],
            'clinic_item_id' => $data['scope'] === 'item' ? $data['clinic_item_id'] : null,
            'item_type' => $data['scope'] === 'type' ? $data['item_type'] : null,
            'branch_id' => $data['branch_id'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    protected function syncTargets(ClinicPromotion $promo, array $data): void
    {
        $promo->items()->sync($data['scope'] === 'items' ? array_values(array_unique($data['item_ids'] ?? [])) : []);
        $promo->packages()->sync($data['scope'] === 'packages' ? array_values(array_unique($data['package_ids'] ?? [])) : []);
    }

    protected function present(ClinicPromotion $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'discount_type' => $p->discount_type,
            'discount_value' => (float) $p->discount_value,
            'scope' => $p->scope,
            'clinic_item_id' => $p->clinic_item_id,
            'clinic_item_name' => $p->clinicItem?->localized_name,
            'item_type' => $p->item_type,
            'item_ids' => $p->items->pluck('id')->all(),
            'item_names' => $p->items->map(fn ($i) => $i->localized_name)->all(),
            'package_ids' => $p->packages->pluck('id')->all(),
            'package_names' => $p->packages->map(fn ($pk) => $pk->localized_name)->all(),
            'branch_id' => $p->branch_id,
            'branch_name' => $p->branch?->localized_name,
            'starts_at' => optional($p->starts_at)->toDateString(),
            'ends_at' => optional($p->ends_at)->toDateString(),
            'priority' => (int) $p->priority,
            'is_active' => (bool) $p->is_active,
        ];
    }
}
