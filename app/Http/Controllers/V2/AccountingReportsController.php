<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Support\ResolvesAccessibleClinics;
use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Services\Accounting\AccountingReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounting financial statements — v2 replacement for the five Filament report
 * pages (Trial Balance, General Ledger, P&L, Balance Sheet, Cash Flow). Each
 * action renders a filter shell instantly and streams the heavy aggregation via
 * Inertia::defer. All numbers come straight from AccountingReportService.
 */
class AccountingReportsController extends Controller
{
    use ResolvesAccessibleClinics;

    public function __construct(protected AccountingReportService $svc) {}

    protected function ensureCan(Request $request, string $permission): void
    {
        if (! $request->user() || ! $request->user()->can($permission)) {
            abort(403, 'Not authorized to view this report.');
        }
    }

    /** From/To range default = month-to-date. */
    protected function range(Request $request): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $from = $request->input('from') ?: Carbon::now($tz)->startOfMonth()->toDateString();
        $to = $request->input('to') ?: Carbon::now($tz)->toDateString();

        return [$from, $to];
    }

    protected function fmtRange(string $from, string $to): array
    {
        return ['from' => $from, 'to' => $to, 'from_label' => Carbon::parse($from)->format('d M Y'), 'to_label' => Carbon::parse($to)->format('d M Y')];
    }

    /** Selected branch id from the request, honoring clinic-access scoping. */
    protected function selectedBranchId(Request $request): ?int
    {
        $id = $request->input('branch_id') ? (int) $request->input('branch_id') : null;
        $accessible = $this->accessibleBranchIds();
        if ($id !== null && $accessible !== null && ! in_array($id, $accessible, true)) {
            return null; // not allowed to scope to that branch — fall back to group view
        }

        return $id;
    }

    /** Branch picker options (scoped to the user's accessible clinics). */
    protected function branchOptions(): array
    {
        return Branch::query()
            ->when($this->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('id', $this->accessibleBranchIds() ?: [0]))
            ->orderBy('id')->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all();
    }

    public function trialBalance(Request $request): Response
    {
        $this->ensureCan($request, 'view_accounting_trial_balance');
        [$from, $to] = $this->range($request);
        $branchId = $this->selectedBranchId($request);

        return Inertia::render('Reports/Accounting/TrialBalance', [
            'filters' => array_merge($this->fmtRange($from, $to), ['branch_id' => $branchId]),
            'branches' => $this->branchOptions(),
            'report' => Inertia::defer(fn () => $this->svc->trialBalance($from, $to, $branchId)),
        ]);
    }

    public function generalLedger(Request $request): Response
    {
        $this->ensureCan($request, 'view_accounting_general_ledger');
        [$from, $to] = $this->range($request);
        $accountId = $request->input('account_id') ? (int) $request->input('account_id') : null;
        $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;

        return Inertia::render('Reports/Accounting/GeneralLedger', [
            'filters' => array_merge($this->fmtRange($from, $to), ['account_id' => $accountId, 'branch_id' => $branchId]),
            'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($a) => ['id' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all(),
            'branches' => Branch::query()->when($this->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('id', $this->accessibleBranchIds() ?: [0]))->orderBy('id')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all(),
            'report' => Inertia::defer(fn () => $this->svc->generalLedger($accountId, $from, $to, $branchId)),
        ]);
    }

    public function profitAndLoss(Request $request): Response
    {
        $this->ensureCan($request, 'view_accounting_profit_and_loss');
        [$from, $to] = $this->range($request);
        $branchId = $this->selectedBranchId($request);

        return Inertia::render('Reports/Accounting/ProfitLoss', [
            'filters' => array_merge($this->fmtRange($from, $to), ['branch_id' => $branchId]),
            'branches' => $this->branchOptions(),
            'report' => Inertia::defer(fn () => $this->svc->profitAndLoss($from, $to, $branchId)),
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $this->ensureCan($request, 'view_accounting_balance_sheet');
        $tz = config('app.timezone', 'Asia/Kuwait');
        $asOf = $request->input('as_of') ?: Carbon::now($tz)->toDateString();
        $branchId = $this->selectedBranchId($request);

        return Inertia::render('Reports/Accounting/BalanceSheet', [
            'filters' => ['as_of' => $asOf, 'as_of_label' => Carbon::parse($asOf)->format('d M Y'), 'branch_id' => $branchId],
            'branches' => $this->branchOptions(),
            'report' => Inertia::defer(fn () => $this->svc->balanceSheet($asOf, $branchId)),
        ]);
    }

    public function aging(Request $request): Response
    {
        $this->ensureCan($request, 'view_accounting_general_ledger');
        $tz = config('app.timezone', 'Asia/Kuwait');
        $asOf = $request->input('as_of') ?: Carbon::now($tz)->toDateString();
        $mode = $request->input('mode') === 'ap' ? 'ap' : 'ar';
        $branchId = $this->selectedBranchId($request);
        $svc = app(\App\Services\Accounting\AgingService::class);

        return Inertia::render('Reports/Accounting/Aging', [
            'filters' => ['as_of' => $asOf, 'as_of_label' => Carbon::parse($asOf)->format('d M Y'), 'mode' => $mode, 'branch_id' => $branchId],
            'branches' => $this->branchOptions(),
            'report' => Inertia::defer(fn () => $mode === 'ap'
                ? $svc->accountsPayable($asOf, $branchId)
                : $svc->accountsReceivable($asOf, $branchId)),
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $this->ensureCan($request, 'view_accounting_cash_flow');
        [$from, $to] = $this->range($request);

        return Inertia::render('Reports/Accounting/CashFlow', [
            'filters' => $this->fmtRange($from, $to),
            'report' => Inertia::defer(fn () => $this->svc->cashFlow($from, $to)),
            'can_view_posting' => (bool) $request->user()?->can('view_any_accounting_accounts'),
        ]);
    }
}
