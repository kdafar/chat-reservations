<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicPaymentMethod;
use App\Models\Partner;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payment methods offered in the visit-payment + check-in modals.
 *
 * Scope is layered and resolved by ClinicPaymentMethodResolver, most specific
 * winning per `key`:
 *
 *   global  (partner null, branch null)  — ships with the product
 *   clinic  (partner set,  branch null)  — overrides global for one clinic
 *   branch  (branch set)                 — overrides both for one branch
 *
 * Editing previously existed only in the retired Filament admin, so a clinic
 * had no way to disable a method or add its own. This is the v2 replacement.
 *
 * The model's saving() hook enforces one row per (partner, branch, key) and
 * throws a ValidationException — that surfaces as a normal field error, so no
 * duplicate check is repeated here.
 */
class PaymentMethodsController extends Controller
{
    use ResolvesAccessibleClinics;

    public const TYPES = ['manual', 'online'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_clinic_payment_methods')) {
            abort(403);
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_clinic_payment_methods');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => $request->input('type', ''),
            'active' => $request->input('active', ''),
        ];

        $query = ClinicPaymentMethod::query()->with(['partner:id,name', 'branch:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(fn ($qq) => $qq->where('key', 'like', "%{$q}%")->orWhere('label', 'like', "%{$q}%"));
        }
        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }
        if ($filters['active'] !== '') {
            $query->where('is_active', $filters['active'] === '1');
        }

        $page = $query->orderBy('sort_order')->orderBy('id')->paginate(25)->withQueryString();

        // Partner/Branch names are translatable; flatten for the table columns
        // and label the scope so staff can see which layer a row belongs to.
        $page->getCollection()->each(function (ClinicPaymentMethod $m) {
            $m->scope = $m->branch_id ? 'branch' : ($m->partner_id ? 'clinic' : 'global');
            $m->scope_name = $m->branch_id
                ? ($m->branch?->localized_name ?? '—')
                : ($m->partner_id ? ($m->partner?->name ?? '—') : null);
            $m->unsetRelation('partner')->unsetRelation('branch');
        });

        return Inertia::render('PaymentMethods/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $this->accessibleBranches()->all(),
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->name])->all(),
            'types' => self::TYPES,
            'counts' => [
                'total' => ClinicPaymentMethod::query()->count(),
                'active' => ClinicPaymentMethod::query()->where('is_active', true)->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        ClinicPaymentMethod::create($this->validated($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Payment method added.']);
    }

    public function update(Request $request, ClinicPaymentMethod $payment_method): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        $payment_method->update($this->validated($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Payment method updated.']);
    }

    public function destroy(Request $request, ClinicPaymentMethod $payment_method): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        // Deleting the last row for a key removes that method from every
        // payment modal. Deactivating is nearly always what's meant, and it is
        // reversible, so steer there rather than silently dropping a method.
        $payment_method->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Payment method removed. Deactivate instead if you may want it back.']);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in(self::TYPES)],
            'scope' => ['required', Rule::in(['global', 'clinic', 'branch'])],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $this->scopeBranchRule($q)),
            ],
            'requires_reference' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'key.regex' => 'Use lowercase letters, numbers and underscores only (e.g. apple_pay).',
        ]);

        // The scope selector is the source of truth: blank out the ids the
        // chosen scope doesn't use, so a row can never be half-scoped.
        // (The model additionally back-fills partner_id from branch_id.)
        $scope = $data['scope'];
        unset($data['scope']);

        if ($scope === 'global') {
            $data['partner_id'] = null;
            $data['branch_id'] = null;
        } elseif ($scope === 'clinic') {
            $data['branch_id'] = null;
            $request->validate(['partner_id' => ['required', 'integer', 'exists:partners,id']]);
        } else {
            $request->validate(['branch_id' => ['required', 'integer']]);
        }

        $data['requires_reference'] = (bool) $request->input('requires_reference', false);
        $data['is_active'] = (bool) $request->input('is_active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /** Limit the branch_id rule to the user's clinic (global admin = any). */
    protected function scopeBranchRule($q)
    {
        $ids = $this->accessibleBranchIds();
        if ($ids !== null) {
            $q->whereIn('id', $ids ?: [0]);
        }

        return $q;
    }
}
