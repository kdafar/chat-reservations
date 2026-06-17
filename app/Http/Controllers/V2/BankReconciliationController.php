<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankStatementLine;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bank Reconciliation — v2 replacement for Filament BankReconciliationResource +
 * its StatementLinesRelationManager. State machine in_progress ↔ completed; all
 * balance / matching logic delegated to BankReconciliationService and the models.
 */
class BankReconciliationController extends Controller
{
    public function __construct(protected BankReconciliationService $svc) {}

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_accounting_bank_reconciliations')) {
            abort(403, 'Not authorized to view bank reconciliations.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_accounting_bank_reconciliations');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = ['status' => $request->input('status', 'all')];
        $query = BankReconciliation::query()->with('account:id,code,name')->withCount(['statementLines', 'matchedLines']);
        if (in_array($filters['status'], ['in_progress', 'completed'], true)) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (BankReconciliation $r) {
            $r->setAttribute('diff', round((float) $r->closing_balance - (float) $r->book_closing_balance, 3));
            return $r;
        });

        return Inertia::render('BankReconciliation/Index', [
            'filters' => $filters,
            'page' => $page,
            'accounts' => $this->bankAccountOptions(),
            'statuses' => ['in_progress', 'completed'],
            'counts' => [
                'total' => BankReconciliation::query()->count(),
                'in_progress' => BankReconciliation::query()->where('status', 'in_progress')->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    /** Dedicated "new reconciliation" page (replaces the old create modal). */
    public function create(Request $request): Response
    {
        $this->authorizeAccess($request);
        abort_unless((bool) $request->user()->can('create_accounting_bank_reconciliations'), 403);

        return Inertia::render('BankReconciliation/Form', [
            'accounts' => $this->bankAccountOptions(),
        ]);
    }

    /** Dedicated reconciliation workspace page (replaces the old slide-over drawer). */
    public function show(Request $request, BankReconciliation $bankReconciliation): Response
    {
        $this->authorizeAccess($request);
        $rec = $bankReconciliation;
        $rec->load(['account:id,code,name', 'statementLines.matchedLine.entry:id,code', 'completedBy:id,name']);

        return Inertia::render('BankReconciliation/Show', [
            'rec' => $rec,
            'diff' => round((float) $rec->closing_balance - (float) $rec->book_closing_balance, 3),
            'matchable' => $this->matchableJournalLines($rec),
            'editable' => $rec->status === BankReconciliation::STATUS_IN_PROGRESS,
            'can' => ['edit' => $this->canEdit($request)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('create_accounting_bank_reconciliations')) abort(403);

        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'opening_balance' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
        ]);

        $this->svc->createForAccountAndPeriod(
            (int) $data['account_id'], $data['period_start'], $data['period_end'],
            (float) $data['opening_balance'], (float) $data['closing_balance'],
        );
        return redirect()->route('v2.accounting.bank-rec.index')
            ->with('flash', ['type' => 'success', 'message' => 'Reconciliation created.']);
    }

    public function update(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        if ($bankReconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only in-progress reconciliations can be edited.']);
        }
        $bankReconciliation->update($request->validate([
            'opening_balance' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));
        return back()->with('flash', ['type' => 'success', 'message' => 'Reconciliation updated.']);
    }

    public function recompute(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $this->guardInProgress($request, $bankReconciliation);
        $this->svc->recomputeBalances($bankReconciliation);
        return back()->with('flash', ['type' => 'success', 'message' => 'Book balances recomputed.']);
    }

    public function autoMatch(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $this->guardInProgress($request, $bankReconciliation);
        $count = $this->svc->autoMatch($bankReconciliation);
        return back()->with('flash', ['type' => 'success', 'message' => "Auto-matched {$count} line(s)."]);
    }

    public function complete(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        if ($bankReconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Already completed.']);
        }
        $bankReconciliation->forceFill([
            'status' => BankReconciliation::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by_user_id' => $request->user()->id,
        ])->save();
        return back()->with('flash', ['type' => 'success', 'message' => 'Reconciliation completed.']);
    }

    public function reopen(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        if ($bankReconciliation->status !== BankReconciliation::STATUS_COMPLETED) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only completed reconciliations can be reopened.']);
        }
        $bankReconciliation->forceFill([
            'status' => BankReconciliation::STATUS_IN_PROGRESS,
            'completed_at' => null, 'completed_by_user_id' => null,
        ])->save();
        return back()->with('flash', ['type' => 'success', 'message' => 'Reopened.']);
    }

    public function importCsv(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $this->guardInProgress($request, $bankReconciliation);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']]);
        try {
            // Accept Excel workbooks too: convert to a temp CSV (same column
            // order) so the statement parser keeps working unchanged.
            $upload = $request->file('file');
            $path = $upload->getRealPath();
            $tmpCsv = null;
            if (in_array(strtolower($upload->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
                $tmpCsv = tempnam(sys_get_temp_dir(), 'bankrec_').'.csv';
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path)
                    ->setReadDataOnly(true)->load($path);
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
                $writer->setUseBOM(false);
                $writer->setDelimiter(',');
                $writer->save($tmpCsv);
                $path = $tmpCsv;
            }

            $count = $this->svc->importCsv($bankReconciliation, $path);

            if ($tmpCsv) {
                @unlink($tmpCsv);
            }
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
        return back()->with('flash', ['type' => 'success', 'message' => "Imported {$count} statement line(s)."]);
    }

    public function matchLine(Request $request, BankStatementLine $line): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $data = $request->validate(['journal_entry_line_id' => ['required', 'integer', 'exists:journal_entry_lines,id']]);
        try {
            $line->match((int) $data['journal_entry_line_id'], $request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Line matched.']);
    }

    public function unmatchLine(Request $request, BankStatementLine $line): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $line->unmatch();
        return back()->with('flash', ['type' => 'success', 'message' => 'Line unmatched.']);
    }

    protected function guardInProgress(Request $request, BankReconciliation $rec): void
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        if ($rec->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            abort(422, 'Reconciliation is not in progress.');
        }
    }

    /** Unmatched posted JE lines on this account within the period (mirrors the relation manager). */
    protected function matchableJournalLines(BankReconciliation $rec): array
    {
        $alreadyMatched = $rec->statementLines()
            ->whereNotNull('matched_journal_entry_line_id')
            ->pluck('matched_journal_entry_line_id')->all();

        return JournalEntryLine::query()
            ->where('account_id', $rec->account_id)
            ->whereHas('entry', fn ($q) => $q->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$rec->period_start->toDateString(), $rec->period_end->toDateString()]))
            ->when(! empty($alreadyMatched), fn ($q) => $q->whereNotIn('id', $alreadyMatched))
            ->with('entry:id,code,entry_date')
            ->orderByDesc('id')->limit(500)->get()
            ->map(function (JournalEntryLine $r) {
                $date = $r->entry?->entry_date?->format('Y-m-d') ?? '—';
                $amount = (float) $r->debit > 0 ? 'Dr '.number_format((float) $r->debit, 3) : 'Cr '.number_format((float) $r->credit, 3);
                $desc = $r->description ? ' • '.Str::limit($r->description, 40) : '';
                return ['id' => $r->id, 'label' => "{$date} • ".($r->entry?->code ?? '—')." • {$amount}{$desc}"];
            })->all();
    }

    protected function bankAccountOptions(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where(fn ($q) => $q->where('code', 'like', '1110%')->orWhere('code', 'like', '1120%')->orWhere('code', 'like', '1130%'))
            ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all();
    }
}
