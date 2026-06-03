<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Insurance\InsurancePreauthorization;
use App\Models\Insurance\PatientInsurancePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Insurance Pre-authorizations — v2 replacement for Filament InsurancePreauthorizationResource.
 * Branch-scoped (global scope on the model handles visibility). Services is a JSON
 * repeater of {label, estimated_amount}. Decision mirrors the Filament markDecision
 * handler (direct save of status + approved_amount + notes + decided_at/by).
 */
class PreauthorizationsController extends Controller
{
    private const STATUSES = ['draft', 'submitted', 'under_review', 'approved', 'partially_approved', 'rejected', 'expired'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_insurance_preauthorizations')) {
            abort(403, 'Not authorized to view pre-authorizations.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_insurance_preauthorizations');
    }

    /** Styled .xlsx export of pre-authorizations (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = InsurancePreauthorization::query()->with(['patientPolicy.patient:id,name', 'patientPolicy.plan:id,code']);
        if ($q !== '') {
            $query->where(fn ($qq) => $qq->where('reference_no', 'like', "%{$q}%")
                ->orWhereHas('patientPolicy.patient', fn ($p) => $p->where('name', 'like', "%{$q}%")));
        }
        if (in_array($status, self::STATUSES, true)) { $query->where('status', $status); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['Reference', 'Patient', 'Plan', 'Status', 'Estimated', 'Approved', 'Requested', 'Valid from', 'Valid until'],
                fn ($p) => [$p->reference_no, $p->patientPolicy?->patient?->name, $p->patientPolicy?->plan?->code, $p->status, number_format((float) $p->estimated_total, 3, '.', ''), number_format((float) $p->approved_amount, 3, '.', ''), (string) $p->requested_at, (string) $p->valid_from, (string) $p->valid_until],
                'Preauthorizations',
                app()->getLocale() === 'ar',
            ),
            'preauthorizations-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
        ];

        $query = InsurancePreauthorization::query()
            ->with(['patientPolicy.patient:id,name', 'patientPolicy.plan:id,code']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('reference_no', 'like', "%{$q}%")
                    ->orWhereHas('patientPolicy.patient', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }
        if (in_array($filters['status'], self::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();

        // Deep-link: ?open={id} (e.g. from a pre-auth decision notification) opens
        // that record's editor on load, even when it isn't on the current page.
        $openRecord = null;
        if ($openId = $request->integer('open')) {
            $openRecord = InsurancePreauthorization::query()
                ->with(['patientPolicy.patient:id,name', 'patientPolicy.plan:id,code'])
                ->find($openId);
        }

        return Inertia::render('Preauthorizations/Index', [
            'filters' => $filters,
            'page' => $page,
            'open_record' => $openRecord,
            'policies' => $this->policyOptions(),
            'statuses' => self::STATUSES,
            'counts' => [
                'total' => InsurancePreauthorization::query()->count(),
                'pending' => InsurancePreauthorization::query()->whereIn('status', ['submitted', 'under_review'])->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);

        $data = $this->validated($request);
        $data['requested_by_user_id'] = $request->user()->id;
        $data['branch_id'] = $this->resolveBranchId($data['visit_id'] ?? null);
        if (empty($data['status'])) {
            $data['status'] = 'draft';
        }

        InsurancePreauthorization::create($data);
        return back()->with('flash', ['type' => 'success', 'message' => 'Pre-authorization created.']);
    }

    public function update(Request $request, InsurancePreauthorization $preauthorization): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $preauthorization->update($this->validated($request));
        return back()->with('flash', ['type' => 'success', 'message' => 'Pre-authorization updated.']);
    }

    /** Record a decision — mirrors Filament markDecision (direct save). */
    public function decide(Request $request, InsurancePreauthorization $preauthorization): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);

        if (! in_array($preauthorization->status, ['submitted', 'under_review'], true)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only submitted / under-review requests can be decided.']);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'partially_approved', 'rejected'])],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $preauthorization->forceFill([
            'status' => $data['status'],
            'approved_amount' => $data['approved_amount'] ?? $preauthorization->approved_amount,
            'decision_notes' => $data['decision_notes'] ?? $preauthorization->decision_notes,
            'decided_at' => now(),
            'decided_by_user_id' => $request->user()->id,
        ])->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Decision recorded.']);
    }

    public function destroy(Request $request, InsurancePreauthorization $preauthorization): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $preauthorization->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Pre-authorization deleted.']);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'patient_policy_id' => ['required', 'integer', Rule::exists('patient_insurance_policies', 'id')],
            'visit_id' => ['nullable', 'integer', Rule::exists('visits', 'id')],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'requested_at' => ['nullable', 'date'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.label' => ['required', 'string', 'max:191'],
            'services.*.estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'estimated_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Normalise services + derive estimated_total if not supplied.
        $data['services'] = array_values(array_map(fn ($s) => [
            'label' => $s['label'],
            'estimated_amount' => (float) ($s['estimated_amount'] ?? 0),
        ], $data['services']));
        if (! isset($data['estimated_total']) || $data['estimated_total'] === null) {
            $data['estimated_total'] = array_sum(array_column($data['services'], 'estimated_amount'));
        }
        $data['requested_at'] = $data['requested_at'] ?? now();

        return $data;
    }

    /** Active policies as {id, label} for the request form. */
    protected function policyOptions(): array
    {
        return PatientInsurancePolicy::query()
            ->with(['patient:id,name', 'plan:id,code'])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => trim(($p->patient?->name ?? 'Patient #'.$p->patient_id)
                    .' → '.($p->plan?->code ?? '—').' ('.$p->policy_number.')'),
            ])->all();
    }

    /** Pre-auths inherit branch from their visit when one is linked; else global (null). */
    protected function resolveBranchId(?int $visitId): ?int
    {
        if (! $visitId) {
            return null;
        }
        return \App\Models\Visit::withoutGlobalScopes()->whereKey($visitId)->value('branch_id');
    }
}
