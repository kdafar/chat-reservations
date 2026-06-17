<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Visit;
use App\Models\VisitPayment;
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

    /** Balance owed to us, as a SQL expression — reused for the "outstanding" sort + filter. */
    private const BALANCE_SQL = '(insurer_payable - paid_amount - write_off_amount - rejected_amount)';

    private const SORTS = ['recent', 'outstanding', 'aging'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_insurance_claims')) {
            abort(403, 'Not authorized to view insurance claims.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $q = trim((string) $request->input('q', ''));
        // A search should look across every claim, not just the current tab —
        // landing on "Needs action" must never hide a paid claim someone looks up.
        $status = $q !== '' ? 'all' : (string) $request->input('status', 'needs_action');
        $insurer = (int) $request->input('insurer', 0);
        $branch = (int) $request->input('branch', 0);
        $sort = in_array($request->input('sort'), self::SORTS, true) ? $request->input('sort') : 'recent';

        $filters = ['q' => $q, 'status' => $status, 'insurer' => $insurer ?: null, 'branch' => $branch ?: null, 'sort' => $sort];

        // Insurer/branch scope is applied to BOTH the list and the tab counts so
        // the numbers on the tabs always match the rows the current filter shows.
        $scope = function ($qb) use ($insurer, $branch) {
            if ($insurer) $qb->whereHas('patientPolicy', fn ($p) => $p->where('insurer_id', $insurer));
            if ($branch) $qb->where('branch_id', $branch);
            return $qb;
        };

        $query = InsuranceClaim::query()
            ->with([
                'patientPolicy.patient:id,name',
                'patientPolicy.insurer:id,name',
                'visit:id',
                'submittedBy:id,name',
            ])
            ->tap($scope);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('claim_number', 'like', "%{$q}%")
                    ->orWhere('reference_no', 'like', "%{$q}%")
                    ->orWhereHas('patientPolicy.patient', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        // Tab filters. "needs_action" / "waiting" are workflow buckets, not raw
        // statuses; the rest map straight through.
        if ($status === 'needs_action') {
            $query->where($this->needsActionScope());
        } elseif ($status === 'waiting') {
            $query->whereIn('status', [InsuranceClaim::STATUS_SUBMITTED, InsuranceClaim::STATUS_UNDER_REVIEW]);
        } elseif (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        // Sort: newest, most money owed first, or longest-waiting first (aging).
        match ($sort) {
            'outstanding' => $query->orderByRaw(self::BALANCE_SQL.' DESC')->orderByDesc('id'),
            'aging' => $query->orderByRaw('COALESCE(submitted_at, created_at) ASC')->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        // Filtered totals over the FULL query (not just the visible page). Clone
        // BEFORE paginate so the aggregate sees the same scope/filters as the list.
        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(total_charged), 0) as total_billed')
            ->selectRaw('COALESCE(SUM('.self::BALANCE_SQL.'), 0) as total_outstanding')
            ->reorder()
            ->first();
        $summary = [
            'total_billed' => round((float) ($summaryRow->total_billed ?? 0), 3),
            'total_outstanding' => round((float) ($summaryRow->total_outstanding ?? 0), 3),
        ];

        $page = $query->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (InsuranceClaim $c) {
            $c->setAttribute('balance_due', $c->balanceDue());
            $c->setAttribute('next_step', $this->nextStepFor($c));
            // Age = days a claim has been with us / the insurer. Use submission
            // time once sent, else creation time. Only meaningful while open.
            $ref = $c->submitted_at ?? $c->created_at;
            $c->setAttribute('age_days', $ref ? (int) $ref->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null);
            return $c;
        });

        $byStatus = InsuranceClaim::query()->tap($scope)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Claims/Index', [
            'filters' => $filters,
            'page' => $page,
            'summary' => $summary,
            'statuses' => self::STATUSES,
            'insurers' => $this->insurerOptions(),
            'branches' => $this->branchOptions(),
            'counts' => [
                'total' => (int) $byStatus->sum(),
                'needs_action' => (int) InsuranceClaim::query()->tap($scope)->where($this->needsActionScope())->count(),
                'waiting' => (int) (($byStatus[InsuranceClaim::STATUS_SUBMITTED] ?? 0) + ($byStatus[InsuranceClaim::STATUS_UNDER_REVIEW] ?? 0)),
                'paid' => (int) ($byStatus[InsuranceClaim::STATUS_PAID] ?? 0),
                'rejected' => (int) ($byStatus[InsuranceClaim::STATUS_REJECTED] ?? 0),
                'open' => (int) InsuranceClaim::query()->tap($scope)->whereNotIn('status', ['paid', 'void', 'rejected'])->count(),
            ],
            'can' => $this->capabilities($request),
        ]);
    }

    /** Insurers that actually appear on a claim — keeps the filter list tight. */
    protected function insurerOptions(): array
    {
        $ids = InsuranceClaim::query()
            ->join('patient_insurance_policies', 'patient_insurance_policies.id', '=', 'insurance_claims.patient_policy_id')
            ->distinct()->pluck('patient_insurance_policies.insurer_id')->filter()->all();

        return \App\Models\Insurance\Insurer::query()
            ->whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($i) => ['value' => $i->id, 'label' => $i->name])->all();
    }

    /** Branches that actually have claims (names are translatable → resolve to locale). */
    protected function branchOptions(): array
    {
        $ids = InsuranceClaim::query()->whereNotNull('branch_id')->distinct()->pluck('branch_id')->all();

        return \App\Models\Branch::query()
            ->whereIn('id', $ids)->get(['id', 'name'])
            ->map(fn ($b) => ['value' => $b->id, 'label' => $this->branchName($b)])
            ->sortBy('label')->values()->all();
    }

    /**
     * Query scope for "needs action on our side": a draft to send, or an
     * approved/partial claim with money still owed to us. submitted/under_review
     * sit with the insurer, so they are NOT actionable here.
     */
    protected function needsActionScope(): \Closure
    {
        return function ($q) {
            $q->where('status', InsuranceClaim::STATUS_DRAFT)
                ->orWhere(function ($w) {
                    $w->whereIn('status', [InsuranceClaim::STATUS_APPROVED, InsuranceClaim::STATUS_PARTIALLY_APPROVED])
                        ->whereRaw('(insurer_payable - paid_amount - write_off_amount - rejected_amount) > 0.0005');
                });
        };
    }

    /** Plain "what happens next" hint for a row, mirrored by the UI's Next-step column. */
    protected function nextStepFor(InsuranceClaim $c): string
    {
        return match (true) {
            $c->status === InsuranceClaim::STATUS_DRAFT => 'submit',
            in_array($c->status, [InsuranceClaim::STATUS_SUBMITTED, InsuranceClaim::STATUS_UNDER_REVIEW], true) => 'await',
            in_array($c->status, [InsuranceClaim::STATUS_APPROVED, InsuranceClaim::STATUS_PARTIALLY_APPROVED], true)
                => $c->balanceDue() > 0.0005 ? 'record_payment' : 'settled',
            $c->status === InsuranceClaim::STATUS_PAID => 'settled',
            $c->status === InsuranceClaim::STATUS_REJECTED => 'rejected',
            default => 'none',
        };
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

    /**
     * A visit may only back a claim once it has been served + billed: status is
     * awaiting_payment or completed, and it carries non-zero charges. Excludes
     * created/future/cancelled/no_show and zero-gross visits. Shared by the
     * picker query, the preview, and the draft POST so they can't disagree.
     */
    protected const CLAIMABLE_STATUSES = [Visit::STATUS_AWAITING_PAYMENT, Visit::STATUS_COMPLETED];

    protected function visitIsClaimable(Visit $visit): bool
    {
        if (! in_array($visit->status, self::CLAIMABLE_STATUSES, true)) {
            return false;
        }

        $gross = (float) ($visit->fees_total ?? 0)
            + (float) ($visit->packages_price_total ?? 0)
            + (float) ($visit->items_price_total ?? 0);

        return $gross > 0.0005;
    }

    /**
     * JSON: recent visits that are claimable — the patient has an active policy
     * AND there is no existing non-void claim for the visit. Powers the searchable
     * visit picker in the "draft a claim" modal. Optional ?q= matches the patient
     * name or the visit booking_code. Returns up to ~20 rows.
     */
    public function claimableVisits(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $q = trim((string) $request->input('q', ''));

        $visits = Visit::query()
            ->with([
                'patient:id,name',
                'branch:id,name',
            ])
            // Patient must have an active *primary* policy — that is the policy a
            // claim is actually built on (createClaimFromVisit), so requiring it
            // here stops a visit being offered that would then fail to draft.
            ->whereIn('patient_id', PatientInsurancePolicy::query()
                ->active()
                ->where('is_primary', true)
                ->select('patient_id'))
            // No existing non-void claim already drafted for this visit.
            ->whereDoesntHave('insuranceClaims', function ($c) {
                $c->where('status', '!=', InsuranceClaim::STATUS_VOID);
            })
            // Only visits that have actually been served + billed are claimable —
            // never created/future/cancelled/no_show, and never a zero-charge visit.
            ->whereIn('status', self::CLAIMABLE_STATUSES)
            ->whereRaw('(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0)) > 0.0005')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('booking_code', 'like', "%{$q}%")
                        ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $rows = $visits->map(function (Visit $visit) {
            $policy = $this->insurance->primaryPolicyFor($visit->patient);
            $policy?->loadMissing(['insurer:id,name', 'plan:id,code,name']);

            return [
                'id' => $visit->id,
                'booking_code' => $visit->booking_code,
                'patient_name' => $visit->patient?->name,
                'branch' => $this->branchName($visit->branch),
                'date' => optional($visit->created_at)->toDateString(),
                'primary_policy' => $policy ? [
                    'insurer' => $policy->insurer?->name,
                    'plan' => $policy->plan?->name,
                    'policy_number' => $policy->policy_number,
                ] : null,
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * JSON: coverage preview for a chosen visit, used by the draft modal before
     * the user commits. Per-kind rows (gross / insurer covers / coverage % /
     * patient copay), totals, already-paid (sum of paid VisitPayments) and the
     * primary-policy header. Also flags whether a claim already exists.
     */
    public function previewVisit(Request $request, Visit $visit): JsonResponse
    {
        $this->authorizeAccess($request);

        if (! $this->visitIsClaimable($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit is not in a claimable state.'], 422);
        }

        $visit->loadMissing(['patient:id,name', 'branch:id,name']);

        $policy = $this->insurance->primaryPolicyFor($visit->patient);
        $policy?->loadMissing(['insurer:id,name', 'plan:id,code,name']);

        // Estimate against the SAME single policy the claim will be built on, so
        // the preview the user signs off on equals the draft they get. With no
        // primary policy there is nothing to draft — return an empty breakdown.
        $estimate = $policy
            ? $this->insurance->estimateForPolicy($visit, $policy)
            : ['by_kind' => [], 'totals' => ['gross' => 0, 'patient_total' => 0, 'insurer_total' => 0]];

        $rows = [];
        foreach (($estimate['by_kind'] ?? []) as $kind => $bucket) {
            $gross = round((float) ($bucket['gross'] ?? 0), 3);
            $copay = round((float) ($bucket['patient_copay'] ?? 0), 3);
            $insurerCovers = round((float) array_sum(array_column($bucket['insurer_portions'] ?? [], 'amount')), 3);
            $rows[] = [
                'kind' => $bucket['kind'] ?? $kind,
                'gross' => $gross,
                'insurer_covers' => $insurerCovers,
                'patient_copay' => $copay,
                'coverage_pct' => $gross > 0 ? round(($insurerCovers / $gross) * 100, 1) : 0.0,
            ];
        }

        $totals = $estimate['totals'] ?? ['gross' => 0, 'patient_total' => 0, 'insurer_total' => 0];

        $alreadyPaid = round((float) VisitPayment::query()
            ->where('visit_id', $visit->getKey())
            ->where('status', 'paid')
            ->sum('amount'), 3);

        $claimExists = InsuranceClaim::query()
            ->where('visit_id', $visit->getKey())
            ->where('status', '!=', InsuranceClaim::STATUS_VOID)
            ->exists();

        return response()->json([
            'visit' => [
                'id' => $visit->id,
                'booking_code' => $visit->booking_code,
                'patient_name' => $visit->patient?->name,
                'branch' => $this->branchName($visit->branch),
                'date' => optional($visit->created_at)->toDateString(),
            ],
            'primary_policy' => $policy ? [
                'insurer' => $policy->insurer?->name,
                'plan' => $policy->plan?->name,
                'policy_number' => $policy->policy_number,
            ] : null,
            'rows' => $rows,
            'totals' => [
                'gross' => round((float) ($totals['gross'] ?? 0), 3),
                'insurer_total' => round((float) ($totals['insurer_total'] ?? 0), 3),
                'patient_total' => round((float) ($totals['patient_total'] ?? 0), 3),
            ],
            'already_paid' => $alreadyPaid,
            'claim_exists' => $claimExists,
            'has_policy' => (bool) $policy,
        ]);
    }

    /** Branch names are translatable arrays/JSON; resolve to the current locale string. */
    protected function branchName($branch): ?string
    {
        if (! $branch) {
            return null;
        }
        $name = $branch->name;
        if (is_array($name)) {
            return $name[app()->getLocale()] ?? $name['en'] ?? reset($name) ?: null;
        }

        return $name;
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

        if (! $this->visitIsClaimable($visit)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'This visit cannot be claimed — it must be completed or awaiting payment, with charges on it.']);
        }

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
        if (! $request->user()->can('insurance_void')) abort(403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        return $this->transition($request, $claim, InsuranceClaim::STATUS_VOID, null, $data['reason'], []);
    }

    public function recordPayment(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('insurance_record_payment')) abort(403);

        // Never let a payment exceed what is still owed — that would drive
        // balanceDue() negative. Cap at the current outstanding balance.
        $balance = number_format(max(0, $this->roundedBalance($claim)), 3, '.', '');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.001', 'max:'.$balance],
            'method' => ['required', Rule::in(['cheque', 'transfer', 'cash'])],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'deposited_to_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')],
        ], ['amount.max' => "Amount can't exceed the outstanding balance of KWD {$balance}."]);

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

        // A write-off can't forgive more than is still outstanding.
        $balance = number_format(max(0, $this->roundedBalance($claim)), 3, '.', '');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.001', 'max:'.$balance],
            'reason' => ['required', 'string', 'max:2000'],
        ], ['amount.max' => "Amount can't exceed the outstanding balance of KWD {$balance}."]);

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

    /** Outstanding balance rounded to fils — the cap for payments and write-offs. */
    protected function roundedBalance(InsuranceClaim $claim): float
    {
        return round($claim->balanceDue(), 3);
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
            'void' => (bool) $u?->can('insurance_void'),
        ];
    }

    /** Bank + cash asset accounts for the "deposited to" select (mirrors Filament). */
    protected function bankAndCashAccountOptions(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('code', ['1110', '1120', '1130'])
                    ->orWhere('code', 'like', '1110-%')
                    ->orWhere('code', 'like', '1120-%');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $a) => ['id' => $a->id, 'label' => "{$a->code} — {$a->name}"])
            ->all();
    }
}
