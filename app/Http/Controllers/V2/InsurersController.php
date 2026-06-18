<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Insurance\Insurer;
use App\Services\Accounting\ChartOfAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Insurers — v2 replacement for Filament InsurerResource.
 * Access: clinic_admin or admin (insurance_view perm).
 */
class InsurersController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_insurers')) {
            abort(403, 'Not authorized to view insurers.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_insurers');
    }

    /** Styled .xlsx export of insurers (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $active = $request->input('active', 'all');

        $query = Insurer::query()->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])->withCount('plans');
        if ($q !== '') {
            $query->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('name_ar', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")->orWhere('tax_id', 'like', "%{$q}%"));
        }
        if ($active === 'active') { $query->where('is_active', true)->whereNull('deleted_at'); }
        elseif ($active === 'inactive') { $query->where(fn ($x) => $x->where('is_active', false)->orWhereNotNull('deleted_at')); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('name'),
                ['ID', 'Name', 'Name (AR)', 'Code', 'Tax ID', 'Payment terms (days)', 'Plans', 'Active'],
                fn ($i) => [$i->id, $i->name, $i->name_ar, $i->code, $i->tax_id, $i->payment_terms_days, $i->plans_count, $i->is_active ? 'Yes' : 'No'],
                'Insurers',
                app()->getLocale() === 'ar',
            ),
            'insurers-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'active' => $request->input('active', 'all'),
        ];

        $query = Insurer::query()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
            ->with('arAccount:id,code,name')
            ->withCount('plans');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('name_ar', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('tax_id', 'like', "%{$q}%");
            });
        }
        if ($filters['active'] === 'active') {
            $query->where('is_active', true)->whereNull('deleted_at');
        } elseif ($filters['active'] === 'inactive') {
            $query->where(function ($q) {
                $q->where('is_active', false)->orWhereNotNull('deleted_at');
            });
        }

        $page = $query->orderBy('name')->paginate(25)->withQueryString();
        $page->getCollection()->each(function (Insurer $ins) {
            $ins->ar_account_label = $ins->arAccount ? $ins->arAccount->code.' — '.$ins->arAccount->name : null;
        });

        $counts = [
            'total' => Insurer::query()->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])->count(),
            'active' => Insurer::query()->where('is_active', true)->count(),
        ];

        return Inertia::render('Insurers/Index', [
            'filters' => $filters,
            'page' => $page,
            'counts' => $counts,
            'can_edit' => $this->canEdit($request),
            'can_edit_accounting' => (bool) $request->user()?->can('update_accounting_accounts'),
            'accounts' => Account::postableOptions([Account::TYPE_ASSET]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        Insurer::create($this->validated($request));
        $this->refreshAccountingIfPermitted($request);
        return back()->with('flash', ['type' => 'success', 'message' => 'Insurer added.']);
    }

    public function update(Request $request, Insurer $insurer): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $insurer->update($this->validated($request, $insurer->id));
        $this->refreshAccountingIfPermitted($request);
        return back()->with('flash', ['type' => 'success', 'message' => 'Insurer updated.']);
    }

    /** Drop the ChartOfAccounts cache so an AR-account change applies at once. */
    protected function refreshAccountingIfPermitted(Request $request): void
    {
        if ($request->user()?->can('update_accounting_accounts')) {
            app(ChartOfAccounts::class)->refresh();
        }
    }

    public function destroy(Request $request, Insurer $insurer): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $insurer->delete();
        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Insurer archived.',
            'undo' => ['url' => route('v2.insurance.insurers.restore', ['insurer' => $insurer->id]), 'label' => 'Undo'],
        ]);
    }

    public function restore(Request $request, int $insurer): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        Insurer::withTrashed()->findOrFail($insurer)->restore();
        return back()->with('flash', ['type' => 'success', 'message' => 'Insurer restored.']);
    }

    /** Archive a set of insurers (soft delete) with a single Undo. */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $ids = $this->validatedIds($request);
        if (empty($ids)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Nothing selected.']);
        }
        Insurer::whereIn('id', $ids)->get()->each->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => count($ids).' insurer(s) archived.',
            // Carry the ids in the undo URL so the (separate) undo POST can restore them.
            'undo' => ['url' => route('v2.insurance.insurers.bulk-restore', ['ids' => $ids]), 'label' => 'Undo'],
        ]);
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $ids = $this->validatedIds($request);
        if (! empty($ids)) {
            Insurer::withTrashed()->whereIn('id', $ids)->get()->each->restore();
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Restored.']);
    }

    protected function validatedIds(Request $request): array
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);
        return array_values(array_unique(array_map('intval', $data['ids'] ?? [])));
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'name_ar' => ['nullable', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32', Rule::unique('insurers', 'code')->ignore($exceptId)->whereNull('deleted_at')],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ar_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]) + [
            'is_active' => (bool) $request->input('is_active', true),
        ];

        // Only an accountant may set the AR-account link; otherwise leave it untouched.
        if (! $request->user()?->can('update_accounting_accounts')) {
            unset($data['ar_account_id']);
        }

        return $data;
    }
}
