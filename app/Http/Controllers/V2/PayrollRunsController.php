<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Services\Clinic\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly payroll: create a run for a period/branch, generate payslips
 * (basic + allowances + doctor commission − loans − unpaid leave), approve to
 * accrue salary to the GL, then mark paid to disburse + settle doctor payable
 * and loan balances. Full accounting integration via PayrollService.
 */
class PayrollRunsController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_payroll_runs')) {
            abort(403, 'Not authorized to view payroll.');
        }
    }

    protected function canManage(Request $request): bool
    {
        return (bool) $request->user()?->can('update_payroll_runs');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'status' => $request->input('status', 'all'),
            'year' => $request->input('year', '') !== '' ? (int) $request->input('year') : null,
        ];

        $query = PayrollRun::query()->with('branch:id,name')->withCount('payslips');
        if (in_array($filters['status'], ['draft', 'approved', 'paid', 'cancelled'], true)) {
            $query->where('status', $filters['status']);
        }
        if ($filters['year']) {
            $query->where('period_year', $filters['year']);
        }

        $page = $query->orderByDesc('period_year')->orderByDesc('period_month')->orderByDesc('id')
            ->paginate(20)->withQueryString();
        $page->getCollection()->transform(function (PayrollRun $r) {
            $r->setAttribute('period_label', $r->periodLabel());
            $r->setAttribute('branch_name', $r->branch?->name);

            return $r;
        });

        return Inertia::render('Payroll/Runs/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $this->branchOptions(),
            'counts' => [
                'total' => PayrollRun::count(),
                'draft' => PayrollRun::where('status', 'draft')->count(),
                'paid_net' => round((float) PayrollRun::where('status', 'paid')->sum('total_net'), 3),
            ],
            'can_manage' => $this->canManage($request),
            'current_year' => (int) now()->year,
            'current_month' => (int) now()->month,
        ]);
    }

    public function show(Request $request, PayrollRun $payrollRun): Response
    {
        $this->authorizeAccess($request);

        $payrollRun->load([
            'payslips.user:id,name,email',
            'payslips.lines',
            'branch:id,name',
            'preparedBy:id,name',
            'approvedBy:id,name',
            'accrualJournalEntry:id,code,narration',
            'paymentJournalEntry:id,code,narration',
        ]);

        $payslips = $payrollRun->payslips->map(function ($s) {
            return [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'user_name' => $s->user?->name ?? ('#'.$s->user_id),
                'user_email' => $s->user?->email,
                'is_doctor' => (bool) $s->doctor_id,
                'basic_salary' => (float) $s->basic_salary,
                'allowances_total' => (float) $s->allowances_total,
                'commission_total' => (float) $s->commission_total,
                'gross_pay' => (float) $s->gross_pay,
                'loan_deduction' => (float) $s->loan_deduction,
                'unpaid_leave_deduction' => (float) $s->unpaid_leave_deduction,
                'unpaid_leave_days' => (int) $s->unpaid_leave_days,
                'other_deductions' => (float) $s->other_deductions,
                'deductions_total' => (float) $s->deductions_total,
                'net_pay' => (float) $s->net_pay,
                'lines' => $s->lines->map(fn ($l) => [
                    'kind' => $l->kind, 'source' => $l->source, 'label' => $l->label, 'amount' => (float) $l->amount,
                ])->values(),
            ];
        })->values();

        return Inertia::render('Payroll/Runs/Show', [
            'run' => [
                'id' => $payrollRun->id,
                'period_label' => $payrollRun->periodLabel(),
                'period_year' => $payrollRun->period_year,
                'period_month' => $payrollRun->period_month,
                'branch_id' => $payrollRun->branch_id,
                'branch_name' => $payrollRun->branch?->name,
                'status' => $payrollRun->status,
                'total_earnings' => (float) $payrollRun->total_earnings,
                'total_deductions' => (float) $payrollRun->total_deductions,
                'total_net' => (float) $payrollRun->total_net,
                'total_salary' => (float) $payrollRun->total_salary,
                'total_commission' => (float) $payrollRun->total_commission,
                'total_loan_repaid' => (float) $payrollRun->total_loan_repaid,
                'pay_date' => optional($payrollRun->pay_date)->toDateString(),
                'notes' => $payrollRun->notes,
                'approved_by' => $payrollRun->approvedBy?->name,
                'approved_at' => optional($payrollRun->approved_at)->toDateTimeString(),
                'paid_at' => optional($payrollRun->paid_at)->toDateTimeString(),
                'accrual_entry' => $payrollRun->accrualJournalEntry?->only(['id', 'code', 'narration']),
                'payment_entry' => $payrollRun->paymentJournalEntry?->only(['id', 'code', 'narration']),
            ],
            'payslips' => $payslips,
            'payment_accounts' => $this->paymentAccountOptions(),
            'can_manage' => $this->canManage($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }

        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'pay_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = PayrollRun::where('period_year', $data['period_year'])
            ->where('period_month', $data['period_month'])
            ->where('branch_id', $data['branch_id'] ?? null)
            ->whereNull('deleted_at')->exists();
        if ($exists) {
            return back()->withErrors(['period_month' => 'A payroll run already exists for this period and branch.']);
        }

        $run = PayrollRun::create([
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
            'branch_id' => $data['branch_id'] ?? null,
            'pay_date' => $data['pay_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => PayrollRun::STATUS_DRAFT,
            'prepared_by_user_id' => $request->user()->id,
        ]);

        // Build payslips immediately so the run is reviewable.
        $this->payroll->generate($run);

        return redirect()->route('v2.payroll-runs.show', $run)
            ->with('flash', ['type' => 'success', 'message' => 'Payroll run created and payslips generated.']);
    }

    /** Recompute payslips for a draft run (e.g. after editing profiles/loans). */
    public function generate(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        try {
            $this->payroll->generate($payrollRun);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Payslips regenerated.']);
    }

    public function approve(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        try {
            $this->payroll->approve($payrollRun, $request->user());
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Payroll approved and accrued to the ledger.']);
    }

    public function markPaid(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        $data = $request->validate([
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ]);
        try {
            $this->payroll->markPaid($payrollRun, $request->user(), (int) $data['payment_account_id']);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Payroll marked paid and disbursed.']);
    }

    public function destroy(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        if ($payrollRun->status !== PayrollRun::STATUS_DRAFT) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only draft runs can be deleted.']);
        }
        $payrollRun->payslips()->each(function ($p) {
            $p->lines()->delete();
            $p->delete();
        });
        \App\Models\StaffLoanRepayment::where('payroll_run_id', $payrollRun->id)->delete();
        $payrollRun->delete();

        return redirect()->route('v2.payroll-runs.index')
            ->with('flash', ['type' => 'success', 'message' => 'Draft payroll run deleted.']);
    }

    public function export(Request $request, PayrollRun $payrollRun)
    {
        $this->authorizeAccess($request);
        $query = $payrollRun->payslips()->with('user:id,name,email')->getQuery();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('id'),
                ['Staff', 'Basic', 'Allowances', 'Commission', 'Gross', 'Loan', 'Unpaid leave', 'Other', 'Net pay'],
                fn ($s) => [
                    $s->user?->name, (float) $s->basic_salary, (float) $s->allowances_total, (float) $s->commission_total,
                    (float) $s->gross_pay, (float) $s->loan_deduction, (float) $s->unpaid_leave_deduction,
                    (float) $s->other_deductions, (float) $s->net_pay,
                ],
                'Payroll '.$payrollRun->periodLabel(),
                app()->getLocale() === 'ar',
            ),
            'payroll-'.$payrollRun->periodLabel().'.xlsx',
        );
    }

    protected function branchOptions(): array
    {
        return Branch::query()->orderBy('id')->get()
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('Branch '.$b->id)])->all();
    }

    /** Cash & bank accounts the net pay can be disbursed from. */
    protected function paymentAccountOptions(): array
    {
        return Account::query()
            ->where(fn ($q) => $q->where('code', 'like', '111%')->orWhere('code', 'like', '112%')->orWhere('code', 'like', '113%'))
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->code.' · '.$a->name])->all();
    }
}
