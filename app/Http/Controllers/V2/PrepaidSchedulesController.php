<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\PrepaidSchedule;
use App\Models\Branch;
use App\Services\Accounting\PrepaymentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prepaid-expense register (accountant-managed). The prepayment is assumed
 * already capitalised into a prepaid asset (Dr 1160/1170 / Cr cash) when paid;
 * this register amortises it straight-line — the monthly run posts Dr expense /
 * Cr prepaid asset. See PrepaymentService + accounting:amortize-prepayments.
 */
class PrepaidSchedulesController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('view_any_accounting_accounts'), 403, 'Not authorized to view prepayments.');
    }

    protected function authorizeEdit(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('update_accounting_accounts'), 403, 'Not authorized to manage prepayments.');
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $status = $request->input('status', 'all');
        $query = PrepaidSchedule::query()->with(['branch:id,name', 'prepaidAccount:id,code,name', 'expenseAccount:id,code,name'])
            ->when(in_array($status, ['active', 'completed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id');

        $page = $query->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (PrepaidSchedule $s) => [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'branch' => $s->branch?->localized_name,
            'prepaid_account' => $s->prepaidAccount ? $s->prepaidAccount->code.' — '.$s->prepaidAccount->name : null,
            'expense_account' => $s->expenseAccount ? $s->expenseAccount->code.' — '.$s->expenseAccount->name : null,
            'total_amount' => (float) $s->total_amount,
            'amortized_amount' => (float) $s->amortized_amount,
            'remaining' => round((float) $s->total_amount - (float) $s->amortized_amount, 3),
            'monthly_slice' => $s->monthlySlice(),
            'term_months' => $s->term_months,
            'start_date' => optional($s->start_date)->toDateString(),
            'last_amortized_on' => optional($s->last_amortized_on)->toDateString(),
            'status' => $s->status,
        ]);

        return Inertia::render('PrepaidSchedules/Index', [
            'filters' => ['status' => $status],
            'page' => $page,
            'summary' => [
                'total' => (float) PrepaidSchedule::query()->sum('total_amount'),
                'amortized' => (float) PrepaidSchedule::query()->sum('amortized_amount'),
                'remaining' => round((float) PrepaidSchedule::query()->sum('total_amount') - (float) PrepaidSchedule::query()->sum('amortized_amount'), 3),
                'active' => PrepaidSchedule::query()->where('status', 'active')->count(),
            ],
            'can_edit' => (bool) $request->user()?->can('update_accounting_accounts'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeEdit($request);

        return Inertia::render('PrepaidSchedules/Form', array_merge(['mode' => 'create', 'schedule' => null], $this->formSupport()));
    }

    public function edit(Request $request, PrepaidSchedule $prepaidSchedule): Response
    {
        $this->authorizeEdit($request);

        return Inertia::render('PrepaidSchedules/Form', array_merge([
            'mode' => 'edit',
            'schedule' => [
                'id' => $prepaidSchedule->id,
                'code' => $prepaidSchedule->code,
                'name' => $prepaidSchedule->name,
                'branch_id' => $prepaidSchedule->branch_id,
                'prepaid_account_id' => $prepaidSchedule->prepaid_account_id,
                'expense_account_id' => $prepaidSchedule->expense_account_id,
                'total_amount' => (float) $prepaidSchedule->total_amount,
                'term_months' => $prepaidSchedule->term_months,
                'start_date' => optional($prepaidSchedule->start_date)->toDateString(),
                'notes' => $prepaidSchedule->notes,
                'status' => $prepaidSchedule->status,
                'amortized_amount' => (float) $prepaidSchedule->amortized_amount,
            ],
        ], $this->formSupport()));
    }

    protected function formSupport(): array
    {
        return [
            'prepaid_accounts' => Account::postableOptions([Account::TYPE_ASSET]),
            'expense_accounts' => Account::postableOptions([Account::TYPE_EXPENSE]),
            'branches' => Branch::query()->orderBy('id')->get(['id', 'name'])->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name])->all(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $this->validateData($request);

        $schedule = new PrepaidSchedule($data);
        $schedule->code = 'PRE-'.str_pad((string) (PrepaidSchedule::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        $schedule->created_by_user_id = $request->user()->id;
        $schedule->save();

        return redirect()->route('v2.prepaid-schedules.index')
            ->with('flash', ['type' => 'success', 'message' => 'Prepaid schedule added.']);
    }

    public function update(Request $request, PrepaidSchedule $prepaidSchedule): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $this->validateData($request);

        $prepaidSchedule->fill($data)->save();

        return redirect()->route('v2.prepaid-schedules.index')
            ->with('flash', ['type' => 'success', 'message' => 'Prepaid schedule updated.']);
    }

    public function runAmortization(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $month = $request->input('month') ? Carbon::parse($request->input('month').'-01') : now(config('app.timezone'))->startOfMonth();
        $r = app(PrepaymentService::class)->runForMonth($month, $request->user()->id);

        return back()->with('flash', ['type' => 'success', 'message' => "Amortization {$r['period']}: posted {$r['posted']} of {$r['schedules']} schedules (".number_format($r['total'], 3).' KWD).']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            // Enforce account TYPE server-side (the UI filters, but a crafted
            // request must not wire a non-asset prepaid or non-expense target).
            'prepaid_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('type', Account::TYPE_ASSET)->where('is_active', true)],
            'expense_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('type', Account::TYPE_EXPENSE)->where('is_active', true)],
            'total_amount' => ['required', 'numeric', 'min:0.001'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
