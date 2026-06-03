<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Insurance\InsurancePlan;
use App\Models\Insurance\Insurer;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Insurance Plans — v2 replacement for Filament InsurancePlanResource.
 * Access: clinic_admin or admin. No soft deletes (hard delete, guarded against FK use).
 */
class InsurancePlansController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_insurance_plans')) {
            abort(403, 'Not authorized to view insurance plans.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_insurance_plans');
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $insurerId = $request->input('insurer_id', 'all');
        $active = $request->input('active', 'all');
        $query = InsurancePlan::query()->with('insurer:id,name')->withCount('policies');
        if ($q !== '') { $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('name_ar', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")); }
        if ($insurerId !== 'all' && $insurerId !== '') { $query->where('insurer_id', (int) $insurerId); }
        if ($active === 'active') { $query->where('is_active', true); } elseif ($active === 'inactive') { $query->where('is_active', false); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('code'),
                ['Code', 'Name', 'Name (AR)', 'Insurer', 'Tier', 'Policies', 'Effective from', 'Effective until', 'Active'],
                fn ($p) => [$p->code, $p->name, $p->name_ar, $p->insurer?->name, $p->tier, $p->policies_count, (string) $p->effective_from, (string) $p->effective_until, $p->is_active ? 'Yes' : 'No'],
                'Insurance Plans',
                app()->getLocale() === 'ar',
            ),
            'insurance-plans-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'insurer_id' => $request->input('insurer_id', 'all'),
            'active' => $request->input('active', 'all'),
        ];

        $query = InsurancePlan::query()
            ->with('insurer:id,name,name_ar')
            ->withCount('coverageRules', 'policies');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('name_ar', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }
        if ($filters['insurer_id'] !== 'all' && $filters['insurer_id'] !== '') {
            $query->where('insurer_id', (int) $filters['insurer_id']);
        }
        if ($filters['active'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['active'] === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('InsurancePlans/Index', [
            'filters' => $filters,
            'page' => $page,
            'insurers' => Insurer::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'name_ar'])->map(fn ($i) => [
                    'id' => $i->id, 'name' => $i->name, 'name_ar' => $i->name_ar,
                ]),
            'tiers' => ['platinum', 'gold', 'silver', 'bronze'],
            'counts' => [
                'total' => InsurancePlan::query()->count(),
                'active' => InsurancePlan::query()->where('is_active', true)->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        InsurancePlan::create($this->validated($request));
        return back()->with('flash', ['type' => 'success', 'message' => 'Plan added.']);
    }

    public function update(Request $request, InsurancePlan $plan): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $plan->update($this->validated($request, $plan->id));
        return back()->with('flash', ['type' => 'success', 'message' => 'Plan updated.']);
    }

    public function destroy(Request $request, InsurancePlan $plan): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        try {
            $plan->delete();
        } catch (QueryException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Cannot delete — this plan is in use by policies or coverage rules.']);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Plan deleted.']);
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        return $request->validate([
            'insurer_id' => ['required', 'integer', Rule::exists('insurers', 'id')],
            'tier' => ['nullable', 'string', Rule::in(['platinum', 'gold', 'silver', 'bronze'])],
            'name' => ['required', 'string', 'max:191'],
            'name_ar' => ['nullable', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32', Rule::unique('insurance_plans', 'code')->ignore($exceptId)],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]) + [
            'is_active' => (bool) $request->input('is_active', true),
        ];
    }
}
