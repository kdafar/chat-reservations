<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Branch;
use App\Models\ClinicCoupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clinic coupon codes (applied to a visit at checkout). Admin-managed.
 */
class ClinicCouponsController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorize(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(['admin', 'super_admin', 'clinic_admin'])) {
            abort(403, 'Not authorized to manage coupons.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorize($request);

        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = ClinicCoupon::query()->with('branch:id,name');
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
        }
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (ClinicCoupon $c) => $this->present($c));

        return Inertia::render('Coupons/Index', [
            'filters' => ['q' => $q, 'status' => $status],
            'page' => $page,
            'branches' => Branch::query()->when($this->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('id', $this->accessibleBranchIds() ?: [0]))->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all(),
            'counts' => [
                'total' => ClinicCoupon::query()->count(),
                'active' => ClinicCoupon::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize($request);
        ClinicCoupon::create($this->validated($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Coupon created.']);
    }

    public function update(Request $request, ClinicCoupon $clinicCoupon): RedirectResponse
    {
        $this->authorize($request);
        $clinicCoupon->update($this->validated($request, $clinicCoupon->id));

        return back()->with('flash', ['type' => 'success', 'message' => 'Coupon updated.']);
    }

    public function destroy(Request $request, ClinicCoupon $clinicCoupon): RedirectResponse
    {
        $this->authorize($request);
        $clinicCoupon->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Coupon deleted.']);
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:clinic_coupons,code'.($ignoreId ? ",{$ignoreId}" : '')],
            'name' => ['nullable', 'string', 'max:191'],
            'discount_type' => ['required', 'in:amount,percent'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'stacks_with_promotions' => ['sometimes', 'boolean'],
        ]);

        return [
            'code' => strtoupper(trim($data['code'])),
            'stacks_with_promotions' => (bool) $request->input('stacks_with_promotions', true),
            'name' => $data['name'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => (float) $data['discount_value'],
            'min_subtotal' => (float) ($data['min_subtotal'] ?? 0),
            'max_discount' => $data['discount_type'] === 'percent' ? ($data['max_discount'] ?? null) : null,
            'branch_id' => $data['branch_id'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'is_active' => (bool) $request->input('is_active', true),
        ];
    }

    protected function present(ClinicCoupon $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'discount_type' => $c->discount_type,
            'discount_value' => (float) $c->discount_value,
            'min_subtotal' => (float) $c->min_subtotal,
            'max_discount' => $c->max_discount !== null ? (float) $c->max_discount : null,
            'branch_id' => $c->branch_id,
            'branch_name' => $c->branch?->localized_name,
            'starts_at' => optional($c->starts_at)->toDateString(),
            'ends_at' => optional($c->ends_at)->toDateString(),
            'max_uses' => $c->max_uses,
            'uses_count' => $c->uses_count,
            'is_active' => (bool) $c->is_active,
            'stacks_with_promotions' => (bool) $c->stacks_with_promotions,
        ];
    }
}
