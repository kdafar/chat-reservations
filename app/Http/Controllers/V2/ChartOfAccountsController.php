<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Chart of Accounts — v2 replacement for Filament ChartOfAccountResource.
 * Self-referential tree (parent_id). System accounts (is_system) are locked.
 * Balances are bulk-computed (one grouped query) to avoid N+1 balanceAt() calls.
 */
class ChartOfAccountsController extends Controller
{
    use ResolvesAccessibleClinics;

    private const TYPES = ['asset', 'liability', 'equity', 'revenue', 'cogs', 'expense', 'contra_asset', 'contra_liability', 'contra_revenue'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_accounting_accounts')) {
            abort(403, 'Not authorized to view the chart of accounts.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_accounting_accounts');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => $request->input('type', 'all'),
            'active' => $request->input('active', 'all'),
        ];

        $query = Account::query()->with('parent:id,code,name');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
        }
        if (in_array($filters['type'], self::TYPES, true)) {
            $query->where('type', $filters['type']);
        }
        if ($filters['active'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['active'] === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderBy('code')->paginate(50)->withQueryString();

        // Bulk balances for the rows on this page (avoids N+1 balanceAt()).
        $ids = $page->getCollection()->pluck('id')->all();
        $sums = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED])
            ->whereIn('journal_entry_lines.account_id', $ids ?: [0])
            ->groupBy('journal_entry_lines.account_id')
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as d, SUM(journal_entry_lines.credit) as c')
            ->get()->keyBy('account_id');

        $page->getCollection()->transform(function (Account $a) use ($sums) {
            $s = $sums->get($a->id);
            $d = (float) ($s->d ?? 0);
            $c = (float) ($s->c ?? 0);
            $a->setAttribute('balance', round($a->isDebitNormal() ? $d - $c : $c - $d, 3));
            return $a;
        });

        return Inertia::render('ChartOfAccounts/Index', [
            'filters' => $filters,
            'page' => $page,
            'types' => self::TYPES,
            'parents' => Account::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn ($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name])->all(),
            'branches' => $this->accessibleBranches()->all(),
            'counts' => [
                'total' => Account::query()->count(),
                'active' => Account::query()->where('is_active', true)->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('create_accounting_accounts')) abort(403);
        Account::create($this->validated($request) + ['is_system' => false]);
        return back()->with('flash', ['type' => 'success', 'message' => 'Account added.']);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        if ($account->is_system) {
            // System accounts: only is_active + description are safe to change.
            $account->update($request->validate([
                'is_active' => ['sometimes', 'boolean'],
                'description' => ['nullable', 'string', 'max:1000'],
            ]) + ['is_active' => (bool) $request->input('is_active', $account->is_active)]);
            return back()->with('flash', ['type' => 'success', 'message' => 'System account updated.']);
        }
        $account->update($this->validated($request, $account->id));
        return back()->with('flash', ['type' => 'success', 'message' => 'Account updated.']);
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('delete_accounting_accounts')) abort(403);
        if ($account->is_system) {
            return back()->with('flash', ['type' => 'error', 'message' => 'System accounts cannot be deleted.']);
        }
        try {
            $account->delete();
        } catch (QueryException $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Cannot delete — this account has journal lines or child accounts.']);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Account deleted.']);
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:16', Rule::unique('chart_of_accounts', 'code')->ignore($exceptId)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in(self::TYPES)],
            'parent_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'currency' => ['required', 'string', 'max:3'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]) + [
            'is_active' => (bool) $request->input('is_active', true),
            'currency' => $request->input('currency', 'KWD'),
        ];
    }
}
