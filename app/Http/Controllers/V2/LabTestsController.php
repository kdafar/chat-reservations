<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Lab\LabTest;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lab test catalog — v2 (Inertia/Vue) replacement for Filament LabTestResource.
 *
 * Access mirrors the Filament resource: anyone with `update_lab_tests` can
 * manage the catalog (admin / clinic_admin per the permission seeder).
 * Reception + doctors don't navigate here; they use the LabOrders relation
 * manager on the visit page which queries the model directly.
 */
class LabTestsController extends Controller
{
    use ResolvesAccessibleClinics;

    /**
     * Mirror the Filament resource's `update_lab_tests` gate. Laravel 11
     * removed controller-level $this->middleware(), so the check lives
     * inline here and runs at the start of every public action.
     */
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_lab_tests')) {
            abort(403, 'Not authorized to view the lab test catalog.');
        }
    }

    /** Mutations require the stronger update permission. */
    protected function authorizeEdit(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('update_lab_tests')) {
            abort(403, 'Not authorized to manage the lab test catalog.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $branchId = $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null;
        $active = $request->input('active', 'all');
        $query = LabTest::query()->with('branch:id,name');
        if ($q !== '') { $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")); }
        if ($branchId) { $query->where('branch_id', $branchId); }
        if ($active === 'active') { $query->where('is_active', true); } elseif ($active === 'inactive') { $query->where('is_active', false); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('code'),
                ['ID', 'Code', 'Name', 'Branch', 'Specimen', 'Unit', 'Reference range', 'Price', 'Active'],
                fn ($t) => [$t->id, $t->code, $t->name, $t->branch?->localized_name, $t->specimen_type, $t->unit, $t->reference_range, number_format((float) $t->default_price, 3, '.', ''), $t->is_active ? 'Yes' : 'No'],
                'Lab Tests',
                app()->getLocale() === 'ar',
            ),
            'lab-tests-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'active' => $request->input('active', 'all'), // all | active | inactive
        ];

        $query = LabTest::query()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
            ->with('branch:id,name');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('specimen_type', 'like', "%{$q}%");
            });
        }
        if ($filters['branch_id'] !== null) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['active'] === 'active') {
            $query->where('is_active', true)->whereNull('deleted_at');
        } elseif ($filters['active'] === 'inactive') {
            $query->where(function ($q) {
                $q->where('is_active', false)->orWhereNotNull('deleted_at');
            });
        }

        $page = $query->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        // Collapse translatable Branch.name relation to the localized string for the table column.
        $page->getCollection()->each(function ($t) {
            if ($t->relationLoaded('branch') && $t->branch) {
                $t->setRelation('branch', new \Illuminate\Support\Fluent([
                    'id' => $t->branch->id,
                    'name' => $t->branch->localized_name,
                ]));
            }
        });

        $branches = $this->accessibleBranches()->all();

        $counts = [
            'total' => LabTest::query()
                ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])->count(),
            'active' => LabTest::query()->where('is_active', true)->count(),
            'inactive' => LabTest::query()
                ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
                ->where(function ($q) {
                    $q->where('is_active', false)->orWhereNotNull('deleted_at');
                })->count(),
        ];

        return Inertia::render('LabTests/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $branches,
            'counts' => $counts,
            'can_edit' => (bool) $request->user()?->can('update_lab_tests'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $this->validated($request);
        LabTest::create($data);

        return redirect()
            ->route('v2.lab-tests.index', $request->only(['q', 'branch_id', 'active']))
            ->with('flash', ['type' => 'success', 'message' => 'Lab test added.']);
    }

    public function update(Request $request, LabTest $labTest): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $this->validated($request, $labTest->id);
        $labTest->update($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Lab test updated.']);
    }

    public function destroy(Request $request, LabTest $labTest): RedirectResponse
    {
        $this->authorizeEdit($request);
        // Soft delete — preserves historical lab_order_items references.
        $labTest->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Lab test archived.']);
    }

    public function restore(Request $request, int $labTest): RedirectResponse
    {
        $this->authorizeEdit($request);
        $test = LabTest::withTrashed()->findOrFail($labTest);
        $test->restore();

        return back()->with('flash', ['type' => 'success', 'message' => 'Lab test restored.']);
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('lab_tests', 'code')
                    ->where(fn ($q) => $q->where('branch_id', $request->input('branch_id'))->whereNull('deleted_at'))
                    ->ignore($exceptId),
            ],
            'name' => ['required', 'string', 'max:191'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'specimen_type' => ['nullable', 'string', 'max:64'],
            'unit' => ['nullable', 'string', 'max:32'],
            'reference_range' => ['nullable', 'string', 'max:191'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => (bool) $request->input('is_active', true),
        ];
    }
}
