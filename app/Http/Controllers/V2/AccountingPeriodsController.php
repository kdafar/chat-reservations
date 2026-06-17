<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\PeriodCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounting Periods — v2 replacement for the Filament AccountingPeriodResource.
 *
 * Periods are auto-created and immutable; this screen is read-only except for
 * the close/reopen lifecycle, which is admin-only and delegates to
 * PeriodCloseService (it posts/reverses the closing journal entry).
 */
class AccountingPeriodsController extends Controller
{
    public function __construct(protected PeriodCloseService $svc) {}

    protected function authorizeView(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_accounting_periods')) {
            abort(403, 'Not authorized to view accounting periods.');
        }
    }

    protected function authorizeClose(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('update_accounting_periods')) {
            abort(403, 'Not authorized to close or reopen periods.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $filters = [
            'status' => $request->input('status', 'all'),
            'year' => $request->input('year') ? (int) $request->input('year') : null,
        ];

        $query = AccountingPeriod::query()->with('closedBy:id,name');

        if (in_array($filters['status'], [AccountingPeriod::STATUS_OPEN, AccountingPeriod::STATUS_CLOSED], true)) {
            $query->where('status', $filters['status']);
        }
        if ($filters['year']) {
            $query->whereYear('start_date', $filters['year']);
        }

        $periods = $query->orderByDesc('start_date')->get()
            ->map(fn (AccountingPeriod $p) => $this->present($p))->all();

        $years = AccountingPeriod::query()
            ->selectRaw('YEAR(start_date) as y')->distinct()->orderByDesc('y')->pluck('y')
            ->map(fn ($y) => (int) $y)->all();

        return Inertia::render('AccountingPeriods/Index', [
            'filters' => $filters,
            'periods' => $periods,
            'years' => $years,
            'canManage' => (bool) $request->user()?->can('update_accounting_periods'),
            'counts' => [
                'open' => AccountingPeriod::query()->where('status', AccountingPeriod::STATUS_OPEN)->count(),
                'closed' => AccountingPeriod::query()->where('status', AccountingPeriod::STATUS_CLOSED)->count(),
            ],
        ]);
    }

    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $this->authorizeClose($request);

        if ($period->status !== AccountingPeriod::STATUS_OPEN) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Period is not open.']);
        }

        try {
            $je = $this->svc->close($period, (int) ($request->user()->id ?? 0));

            return back()->with('flash', ['type' => 'success', 'message' => "Period {$period->code} closed. Closing entry {$je->code} posted."]);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $this->authorizeClose($request);

        if ($period->status !== AccountingPeriod::STATUS_CLOSED) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Period is not closed.']);
        }

        try {
            $this->svc->reopen($period, (int) ($request->user()->id ?? 0));

            return back()->with('flash', ['type' => 'success', 'message' => "Period {$period->code} reopened."]);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    protected function present(AccountingPeriod $p): array
    {
        $closingJeCode = null;
        if ($p->isClosed()) {
            $closingJeCode = JournalEntry::query()
                ->where('source_type', AccountingPeriod::class)
                ->where('source_id', $p->id)
                ->where('status', JournalEntry::STATUS_POSTED)
                ->latest('id')
                ->value('code');
        }

        return [
            'id' => $p->id,
            'code' => $p->code,
            'start_date' => optional($p->start_date)->toDateString(),
            'end_date' => optional($p->end_date)->toDateString(),
            'status' => $p->status,
            'closed_at' => optional($p->closed_at)->format('Y-m-d h:i A'),
            'closed_by' => $p->closedBy?->name,
            'closing_je' => $closingJeCode,
            'notes' => $p->notes,
        ];
    }
}
