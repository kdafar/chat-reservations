<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal Entries — v2 replacement for Filament JournalEntryResource.
 * Manual entries are drafted with balanced debit/credit lines, then posted via
 * JournalEntry::post() (which validates balance + open period). Posted entries are
 * immutable; correction is via reverse() (creates an offsetting posted entry).
 * Create/edit/post/reverse are admin / super_admin only.
 */
class JournalEntriesController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_accounting_journal_entries')) {
            abort(403, 'Not authorized to view journal entries.');
        }
    }

    protected function isAdmin(Request $request): bool
    {
        return (bool) $request->user()?->hasRole(['admin', 'super_admin']);
    }

    /** Styled .xlsx export of journal entries (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');

        $query = JournalEntry::query()->with(['lines', 'branch:id,name']);
        if ($q !== '') {
            $query->where(fn ($qq) => $qq->where('code', 'like', "%{$q}%")->orWhere('narration', 'like', "%{$q}%"));
        }
        if (in_array($status, ['draft', 'posted', 'reversed'], true)) { $query->where('status', $status); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['Code', 'Date', 'Narration', 'Branch', 'Debit', 'Credit', 'Status'],
                fn ($e) => [$e->code, (string) $e->entry_date, $e->narration, $e->branch?->localized_name, number_format((float) $e->totalDebit(), 3, '.', ''), number_format((float) $e->totalCredit(), 3, '.', ''), $e->status],
                'Journal Entries',
                app()->getLocale() === 'ar',
            ),
            'journal-entries-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
        ];

        $query = JournalEntry::query()->with(['lines', 'branch:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(fn ($qq) => $qq->where('code', 'like', "%{$q}%")->orWhere('narration', 'like', "%{$q}%"));
        }
        if (in_array($filters['status'], ['draft', 'posted', 'reversed'], true)) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (JournalEntry $e) {
            $e->setAttribute('total_debit', round($e->totalDebit(), 3));
            $e->setAttribute('total_credit', round($e->totalCredit(), 3));
            $e->setAttribute('source_label', $e->source_type ? class_basename($e->source_type) : null);
            return $e;
        });

        return Inertia::render('JournalEntries/Index', [
            'filters' => $filters,
            'page' => $page,
            'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name])->all(),
            'branches' => $this->accessibleBranches()->all(),
            'statuses' => ['draft', 'posted', 'reversed'],
            'counts' => [
                'total' => JournalEntry::query()->count(),
                'draft' => JournalEntry::query()->where('status', 'draft')->count(),
            ],
            'can_edit' => $this->isAdmin($request),
        ]);
    }

    /** Detail (lines + account labels) for the drawer/edit form. */
    public function show(Request $request, JournalEntry $journalEntry): JsonResponse
    {
        $this->authorizeAccess($request);
        $journalEntry->load(['lines.account:id,code,name', 'postedBy:id,name', 'branch:id,name', 'reversedBy:id,code', 'reversalOf:id,code']);
        return response()->json([
            'entry' => $journalEntry,
            'total_debit' => round($journalEntry->totalDebit(), 3),
            'total_credit' => round($journalEntry->totalCredit(), 3),
            'is_balanced' => $journalEntry->isBalanced(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('create_accounting_journal_entries') || ! $this->isAdmin($request)) abort(403);

        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $entry = JournalEntry::create([
                'entry_date' => $data['entry_date'],
                'branch_id' => $data['branch_id'] ?? null,
                'currency' => $data['currency'] ?? 'KWD',
                'narration' => $data['narration'],
                'status' => JournalEntry::STATUS_DRAFT,
            ]);
            $this->syncLines($entry, $data['lines']);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Draft journal entry created.']);
    }

    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->isAdmin($request)) abort(403);
        if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only draft entries can be edited.']);
        }

        $data = $this->validated($request);
        DB::transaction(function () use ($journalEntry, $data) {
            $journalEntry->update([
                'entry_date' => $data['entry_date'],
                'branch_id' => $data['branch_id'] ?? null,
                'currency' => $data['currency'] ?? 'KWD',
                'narration' => $data['narration'],
            ]);
            $journalEntry->lines()->delete();
            $this->syncLines($journalEntry, $data['lines']);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Draft updated.']);
    }

    public function post(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->isAdmin($request)) abort(403);
        try {
            $journalEntry->post((int) $request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Cannot post: '.$e->getMessage()]);
        }
        return back()->with('flash', ['type' => 'success', 'message' => "Entry {$journalEntry->code} posted."]);
    }

    public function reverse(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->isAdmin($request)) abort(403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try {
            $reversal = $journalEntry->reverse((int) $request->user()->id, $data['reason']);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
        return back()->with('flash', ['type' => 'success', 'message' => "Reversed — created {$reversal->code}."]);
    }

    public function destroy(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->isAdmin($request)) abort(403);
        if ($journalEntry->status !== JournalEntry::STATUS_DRAFT) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only draft entries can be deleted.']);
        }
        DB::transaction(function () use ($journalEntry) {
            $journalEntry->lines()->delete();
            $journalEntry->delete();
        });
        return back()->with('flash', ['type' => 'success', 'message' => 'Draft deleted.']);
    }

    /** Persist lines; each must carry exactly one of debit/credit > 0. */
    protected function syncLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $l) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $l['account_id'],
                'debit' => (float) ($l['debit'] ?? 0),
                'credit' => (float) ($l['credit'] ?? 0),
                'description' => $l['description'] ?? null,
                'branch_id' => $entry->branch_id,
                'currency' => $entry->currency,
            ]);
        }
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'currency' => ['nullable', 'string', 'max:3'],
            'narration' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:191'],
        ]);

        // Each line: exactly one side > 0. Mirrors JournalEntryLine boot() validation
        // but surfaces a friendly field error instead of a 500.
        $tDebit = 0.0; $tCredit = 0.0;
        foreach ($data['lines'] as $i => $l) {
            $d = (float) ($l['debit'] ?? 0); $c = (float) ($l['credit'] ?? 0);
            if (($d > 0 && $c > 0) || ($d == 0 && $c == 0)) {
                throw ValidationException::withMessages(["lines.$i" => 'Each line needs exactly one of debit or credit (> 0).']);
            }
            $tDebit += $d; $tCredit += $c;
        }
        if (abs($tDebit - $tCredit) > 0.001) {
            throw ValidationException::withMessages([
                'lines' => 'Entry is unbalanced: debits '.number_format($tDebit, 3).' ≠ credits '.number_format($tCredit, 3).'.',
            ]);
        }

        return $data;
    }
}
