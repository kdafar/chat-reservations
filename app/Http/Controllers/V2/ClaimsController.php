<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Insurance\InsuranceClaim;
use App\Models\Visit;
use App\Services\Insurance\ClaimStateMachine;
use App\Services\Insurance\InsuranceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Insurance Claims — v2 replacement for Filament InsuranceClaimResource.
 * Claims are auto-drafted from completed visits (VisitObserver); this page lists
 * them, shows detail (items / payments / state log) and drives the state machine
 * through InsuranceService — never mutating status by hand.
 */
class ClaimsController extends Controller
{
    public function __construct(
        protected InsuranceService $insurance,
        protected ClaimStateMachine $machine,
    ) {}

    private const STATUSES = ['draft', 'submitted', 'under_review', 'approved', 'partially_approved', 'rejected', 'paid', 'void'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_insurance_claims')) {
            abort(403, 'Not authorized to view insurance claims.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
        ];

        $query = InsuranceClaim::query()
            ->with([
                'patientPolicy.patient:id,name',
                'patientPolicy.insurer:id,name',
                'visit:id',
            ]);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('claim_number', 'like', "%{$q}%")
                    ->orWhere('reference_no', 'like', "%{$q}%")
                    ->orWhereHas('patientPolicy.patient', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }
        if (in_array($filters['status'], self::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (InsuranceClaim $c) {
            $c->setAttribute('balance_due', $c->balanceDue());
            return $c;
        });

        return Inertia::render('Claims/Index', [
            'filters' => $filters,
            'page' => $page,
            'statuses' => self::STATUSES,
            'counts' => [
                'total' => InsuranceClaim::query()->count(),
                'open' => InsuranceClaim::query()->whereNotIn('status', ['paid', 'void', 'rejected'])->count(),
            ],
            'can' => $this->capabilities($request),
        ]);
    }

    /** Stream selected claims as CSV (bulk export). Not an Inertia response. */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $query = InsuranceClaim::query()
            ->with(['patientPolicy.patient:id,name', 'patientPolicy.insurer:id,name'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderByDesc('id');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query,
                ['Claim #', 'Patient', 'Insurer', 'Charged', 'Payable', 'Paid', 'Balance', 'Status'],
                fn ($c) => [
                    $c->claim_number,
                    $c->patientPolicy?->patient?->name,
                    $c->patientPolicy?->insurer?->name,
                    number_format((float) $c->total_charged, 3, '.', ''),
                    number_format((float) $c->insurer_payable, 3, '.', ''),
                    number_format((float) $c->paid_amount, 3, '.', ''),
                    number_format((float) $c->balanceDue(), 3, '.', ''),
                    $c->status,
                ],
                'Insurance Claims',
                app()->getLocale() === 'ar',
            ),
            'claims-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /** Detail payload for the drawer: items, payments, state log, allowed next states. */
    public function show(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $this->authorizeAccess($request);
        $claim->load([
            'patientPolicy.patient:id,name,phone',
            'patientPolicy.insurer:id,name',
            'patientPolicy.plan:id,code,name',
            'visit:id',
            'items',
            'payments',
            'stateLogs.changedBy:id,name',
        ]);

        return response()->json([
            'claim' => $claim,
            'balance_due' => $claim->balanceDue(),
            'allowed_next' => $this->machine->allowedNextStates($claim->status),
            'accounts' => $this->bankAndCashAccountOptions(),
            'can' => $this->capabilities($request),
        ]);
    }

    /** Draft a claim from a completed visit's primary policy (proper coverage-calc path). */
    public function createFromVisit(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('create_insurance_claims')) abort(403);

        $data = $request->validate([
            'visit_id' => ['required', 'integer', Rule::exists('visits', 'id')],
        ]);

        $visit = Visit::query()->findOrFail($data['visit_id']);
        $policy = $this->insurance->primaryPolicyFor($visit->patient);
        if (! $policy) {
            return back()->with('flash', ['type' => 'error', 'message' => 'That visit\'s patient has no active insurance policy.']);
        }

        $claim = $this->insurance->createClaimFromVisit($visit, $policy, $request->user());
        return back()->with('flash', ['type' => 'success', 'message' => "Claim {$claim->claim_number} drafted."]);
    }

    public function submit(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        return $this->runTransition($request, $claim, InsuranceClaim::STATUS_SUBMITTED, 'insurance_submit_claim', [
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function review(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        return $this->runTransition($request, $claim, InsuranceClaim::STATUS_UNDER_REVIEW, 'insurance_decide_claim', [
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function approve(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0'],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        return $this->transition($request, $claim, InsuranceClaim::STATUS_APPROVED, 'insurance_decide_claim',
            $data['decision_notes'] ?? null, [
                'approved_amount' => $data['approved_amount'],
                'reference_no' => $data['reference_no'] ?? null,
                'decision_notes' => $data['decision_notes'] ?? null,
            ]);
    }

    public function partiallyApprove(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0'],
            'rejected_amount' => ['required', 'numeric', 'min:0'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        return $this->transition($request, $claim, InsuranceClaim::STATUS_PARTIALLY_APPROVED, 'insurance_decide_claim',
            $data['decision_notes'] ?? null, [
                'approved_amount' => $data['approved_amount'],
                'rejected_amount' => $data['rejected_amount'],
                'decision_notes' => $data['decision_notes'] ?? null,
            ]);
    }

    public function reject(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $data = $request->validate([
            'decision_notes' => ['required', 'string', 'max:2000'],
        ]);
        return $this->transition($request, $claim, InsuranceClaim::STATUS_REJECTED, 'insurance_decide_claim',
            $data['decision_notes'], ['decision_notes' => $data['decision_notes']]);
    }

    public function void(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->hasRole(['admin', 'super_admin'])) abort(403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        return $this->transition($request, $claim, InsuranceClaim::STATUS_VOID, null, $data['reason'], []);
    }

    public function recordPayment(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('insurance_record_payment')) abort(403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.001'],
            'method' => ['required', Rule::in(['cheque', 'transfer', 'cash'])],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'deposited_to_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')],
        ]);

        try {
            $payment = $this->insurance->recordInsurerPayment(
                $claim,
                (float) $data['amount'],
                $data['method'],
                $data['reference_no'] ?? null,
                $data['deposited_to_account_id'] ?? null,
                $request->user(),
            );
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Payment of KWD '.number_format((float) $payment->amount, 3).' recorded.']);
    }

    public function writeOff(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('insurance_writeoff')) abort(403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->insurance->writeOff($claim, (float) $data['amount'], $data['reason'], $request->user());
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Write-off recorded.']);
    }

    /** Transition with only an optional notes field (submit / review). */
    protected function runTransition(Request $request, InsuranceClaim $claim, string $to, string $permission, array $rules): RedirectResponse
    {
        $data = $request->validate($rules);
        return $this->transition($request, $claim, $to, $permission, $data['notes'] ?? null, []);
    }

    /** Shared transition driver: gate → InsuranceService::transition → flash. */
    protected function transition(Request $request, InsuranceClaim $claim, string $to, ?string $permission, ?string $notes, array $payload): RedirectResponse
    {
        $this->authorizeAccess($request);
        if ($permission && ! $request->user()->can($permission)) abort(403);

        try {
            $this->insurance->transition($claim, $to, $request->user(), $notes, $payload);
        } catch (\InvalidArgumentException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Invalid transition: '.$e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Claim updated.']);
    }

    protected function capabilities(Request $request): array
    {
        $u = $request->user();
        return [
            'create' => (bool) $u?->can('create_insurance_claims'),
            'submit' => (bool) $u?->can('insurance_submit_claim'),
            'decide' => (bool) $u?->can('insurance_decide_claim'),
            'pay' => (bool) $u?->can('insurance_record_payment'),
            'writeoff' => (bool) $u?->can('insurance_writeoff'),
            'void' => (bool) $u?->hasRole(['admin', 'super_admin']),
        ];
    }

    /** Bank + cash asset accounts for the "deposited to" select (mirrors Filament). */
    protected function bankAndCashAccountOptions(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('code', ['1010', '1020', '1021', '1022'])
                    ->orWhere('code', 'like', '1010-%')
                    ->orWhere('code', 'like', '1020-%');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $a) => ['id' => $a->id, 'label' => "{$a->code} — {$a->name}"])
            ->all();
    }
}
