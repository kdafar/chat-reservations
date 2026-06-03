<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Insurance\Insurer;
use App\Models\Insurance\InsurancePlan;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Patient Insurance Policies — v2 replacement for Filament PatientInsurancePolicyResource.
 * Patient is chosen via a typeahead (lookup()); insurer→plan is a dependent select
 * resolved client-side from the full (small) plan list passed in props.
 */
class PatientPoliciesController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_patient_insurance_policies')) {
            abort(403, 'Not authorized to view insurance policies.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_patient_insurance_policies');
    }

    /** Styled .xlsx export of patient insurance policies (mirrors filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = PatientInsurancePolicy::query()->with(['patient:id,name,phone', 'insurer:id,name', 'plan:id,code,name']);
        if ($q !== '') {
            $query->where(fn ($qq) => $qq->where('policy_number', 'like', "%{$q}%")->orWhere('member_id', 'like', "%{$q}%")
                ->orWhere('card_number', 'like', "%{$q}%")->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")));
        }
        if (in_array($status, ['active', 'expired', 'suspended', 'cancelled'], true)) { $query->where('status', $status); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['Policy #', 'Member ID', 'Card #', 'Patient', 'Insurer', 'Plan', 'Holder', 'Status', 'Primary', 'Effective from', 'Effective until'],
                fn ($p) => [$p->policy_number, $p->member_id, $p->card_number, $p->patient?->name, $p->insurer?->name, $p->plan?->code, $p->holder_name, $p->status, $p->is_primary ? 'Yes' : 'No', (string) $p->effective_from, (string) $p->effective_until],
                'Patient Policies',
                app()->getLocale() === 'ar',
            ),
            'patient-policies-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
        ];

        $query = PatientInsurancePolicy::query()
            ->with(['patient:id,name,phone', 'insurer:id,name', 'plan:id,code,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('policy_number', 'like', "%{$q}%")
                    ->orWhere('member_id', 'like', "%{$q}%")
                    ->orWhere('card_number', 'like', "%{$q}%")
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
            });
        }
        if (in_array($filters['status'], ['active', 'expired', 'suspended', 'cancelled'], true)) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return Inertia::render('PatientPolicies/Index', [
            'filters' => $filters,
            'page' => $page,
            'insurers' => Insurer::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($i) => ['id' => $i->id, 'name' => $i->name]),
            'plans' => InsurancePlan::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'insurer_id', 'code', 'name'])->map(fn ($p) => [
                    'id' => $p->id, 'insurer_id' => $p->insurer_id, 'code' => $p->code, 'name' => $p->name,
                ]),
            'statuses' => ['active', 'expired', 'suspended', 'cancelled'],
            'relationships' => ['self', 'spouse', 'child', 'parent', 'other'],
            'counts' => [
                'total' => PatientInsurancePolicy::query()->count(),
                'active' => PatientInsurancePolicy::query()->where('status', 'active')->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    /** Patient typeahead for the policy form (name / phone / civil id). */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }
        $results = Patient::query()
            ->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('civil_id', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'civil_id'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'label' => trim($p->name.' · '.($p->phone ?: '—').($p->civil_id ? ' · '.$p->civil_id : '')),
            ]);

        return response()->json(['results' => $results]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        PatientInsurancePolicy::create($this->validated($request));
        return back()->with('flash', ['type' => 'success', 'message' => 'Policy added.']);
    }

    public function update(Request $request, PatientInsurancePolicy $policy): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $policy->update($this->validated($request));
        return back()->with('flash', ['type' => 'success', 'message' => 'Policy updated.']);
    }

    public function destroy(Request $request, PatientInsurancePolicy $policy): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        try {
            $policy->delete();
        } catch (QueryException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Cannot delete — this policy has claims or pre-authorizations.']);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Policy deleted.']);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')],
            'insurer_id' => ['required', 'integer', Rule::exists('insurers', 'id')],
            'plan_id' => ['nullable', 'integer', Rule::exists('insurance_plans', 'id')],
            'policy_number' => ['required', 'string', 'max:64'],
            'member_id' => ['nullable', 'string', 'max:64'],
            'card_number' => ['nullable', 'string', 'max:64'],
            'holder_relationship' => ['required', 'string', Rule::in(['self', 'spouse', 'child', 'parent', 'other'])],
            'holder_name' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'string', Rule::in(['active', 'expired', 'suspended', 'cancelled'])],
            'is_primary' => ['sometimes', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]) + [
            'is_primary' => (bool) $request->input('is_primary', false),
        ];
    }
}
