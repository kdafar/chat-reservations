<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\StaffLoan;
use App\Models\User;
use App\Services\Clinic\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff loans & salary advances. Created pending, approved to disburse (posts
 * Dr 1130 Loans Receivable / Cr Cash), then repaid by withholding installments
 * from payroll. Outstanding balance + repayment history tracked per loan.
 */
class StaffLoansController extends Controller
{
    public function __construct(protected LoanService $loans) {}

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_staff_loans')) {
            abort(403, 'Not authorized to view staff loans.');
        }
    }

    protected function canManage(Request $request): bool
    {
        return (bool) $request->user()?->can('update_staff_loans');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
            'type' => $request->input('type', 'all'),
        ];

        $query = StaffLoan::query()->with(['user:id,name,email', 'branch:id,name'])->withSum('repayments', 'amount');
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        if (in_array($filters['status'], ['pending', 'active', 'settled', 'cancelled'], true)) {
            $query->where('status', $filters['status']);
        }
        if (in_array($filters['type'], ['loan', 'advance'], true)) {
            $query->where('type', $filters['type']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (StaffLoan $l) {
            $l->setAttribute('user_name', $l->user?->name ?? ('#'.$l->user_id));
            $l->setAttribute('user_email', $l->user?->email);
            $l->setAttribute('branch_name', $l->branch?->name);
            $l->setAttribute('repaid_total', round((float) $l->repayments_sum_amount, 3));

            return $l;
        });

        return Inertia::render('Payroll/Loans/Index', [
            'filters' => $filters,
            'page' => $page,
            'staff_options' => User::query()->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all(),
            'branches' => $this->branchOptions(),
            'counts' => [
                'total' => StaffLoan::count(),
                'active' => StaffLoan::where('status', 'active')->count(),
                'outstanding' => round((float) StaffLoan::whereIn('status', ['active', 'pending'])->sum('outstanding_amount'), 3),
            ],
            'can_manage' => $this->canManage($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        $data = $this->validated($request);

        StaffLoan::create(array_merge($data, [
            'outstanding_amount' => $data['principal_amount'],
            'status' => StaffLoan::STATUS_PENDING,
        ]));

        return back()->with('flash', ['type' => 'success', 'message' => 'Loan recorded (pending approval).']);
    }

    public function update(Request $request, StaffLoan $staffLoan): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        if ($staffLoan->status !== StaffLoan::STATUS_PENDING) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only pending loans can be edited.']);
        }
        $data = $this->validated($request);
        unset($data['user_id']); // immutable
        $staffLoan->update(array_merge($data, ['outstanding_amount' => $data['principal_amount']]));

        return back()->with('flash', ['type' => 'success', 'message' => 'Loan updated.']);
    }

    /** Approve & disburse: posts the GL disbursement and activates the loan. */
    public function approve(Request $request, StaffLoan $staffLoan): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        if ($staffLoan->status !== StaffLoan::STATUS_PENDING) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Loan is not pending.']);
        }
        if ($request->filled('payment_account_id')) {
            $staffLoan->forceFill(['payment_account_id' => (int) $request->input('payment_account_id')])->save();
        }
        $this->loans->approve($staffLoan, $request->user());

        return back()->with('flash', ['type' => 'success', 'message' => 'Loan approved and disbursed.']);
    }

    public function cancel(Request $request, StaffLoan $staffLoan): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        if (! in_array($staffLoan->status, [StaffLoan::STATUS_PENDING, StaffLoan::STATUS_ACTIVE], true)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Loan cannot be cancelled.']);
        }
        // Active loans with repayments shouldn't be silently cancelled.
        if ($staffLoan->status === StaffLoan::STATUS_ACTIVE && $staffLoan->repayments()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Loan has repayments — settle it instead of cancelling.']);
        }
        $staffLoan->forceFill(['status' => StaffLoan::STATUS_CANCELLED])->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Loan cancelled.']);
    }

    public function destroy(Request $request, StaffLoan $staffLoan): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('delete_staff_loans')) {
            abort(403);
        }
        if ($staffLoan->journal_entry_id) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Disbursed loans cannot be deleted (audit trail). Cancel instead.']);
        }
        $staffLoan->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Loan removed.']);
    }

    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $query = StaffLoan::query()->with('user:id,name');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Staff', 'Type', 'Principal', 'Outstanding', 'Installment', 'Status', 'Issued'],
                fn ($l) => [$l->id, $l->user?->name, $l->type, (float) $l->principal_amount, (float) $l->outstanding_amount, (float) $l->installment_amount, $l->status, (string) $l->issued_on],
                'Staff Loans',
                app()->getLocale() === 'ar',
            ),
            'staff-loans-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'type' => ['required', 'in:loan,advance'],
            'principal_amount' => ['required', 'numeric', 'min:0.001'],
            'installment_amount' => ['required', 'numeric', 'min:0', 'lte:principal_amount'],
            'issued_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return [
            'user_id' => $data['user_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'type' => $data['type'],
            'principal_amount' => round((float) $data['principal_amount'], 3),
            'installment_amount' => round((float) $data['installment_amount'], 3),
            'issued_on' => $data['issued_on'],
            'reason' => $data['reason'] ?? null,
        ];
    }

    protected function branchOptions(): array
    {
        return Branch::query()->orderBy('id')->get()
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('Branch '.$b->id)])->all();
    }
}
