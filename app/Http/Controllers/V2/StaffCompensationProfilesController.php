<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StaffCompensationProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff salary structures — basic salary + recurring allowances/deductions
 * + hire/termination dates that feed payroll and end-of-service gratuity.
 * The non-doctor analog of DoctorCompProfilesController.
 */
class StaffCompensationProfilesController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_staff_compensation_profiles')) {
            abort(403, 'Not authorized to view salary profiles.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_staff_compensation_profiles');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'active' => $request->input('active', 'all'),
        ];

        $query = StaffCompensationProfile::query()->with(['user:id,name,email', 'branch:id,name']);
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        if ($filters['active'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['active'] === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (StaffCompensationProfile $p) {
            $p->setAttribute('user_name', $p->user?->name ?? ('#'.$p->user_id));
            $p->setAttribute('user_email', $p->user?->email);
            $p->setAttribute('branch_name', $p->branch?->name);
            $p->setAttribute('allowances_total', $p->allowancesTotal());
            $p->setAttribute('deductions_total', $p->deductionsTotal());
            $p->setAttribute('gross_monthly', round((float) $p->basic_salary + $p->allowancesTotal(), 3));

            return $p;
        });

        // Users who don't yet have a profile (for the create picker).
        $taken = StaffCompensationProfile::query()->pluck('user_id')->all();
        $available = User::query()->whereNotIn('id', $taken)->orderBy('name')
            ->get(['id', 'name', 'email'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all();

        return Inertia::render('Payroll/Profiles/Index', [
            'filters' => $filters,
            'page' => $page,
            'available_users' => $available,
            'branches' => $this->branchOptions(),
            'counts' => [
                'total' => StaffCompensationProfile::query()->count(),
                'active' => StaffCompensationProfile::query()->where('is_active', true)->count(),
                'monthly_basic' => round((float) StaffCompensationProfile::query()->where('is_active', true)->sum('basic_salary'), 3),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }
        $data = $this->validated($request, null);

        if (StaffCompensationProfile::where('user_id', $data['user_id'])->exists()) {
            return back()->withErrors(['user_id' => 'This staff member already has a salary profile.']);
        }

        StaffCompensationProfile::create($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Salary profile created.']);
    }

    public function update(Request $request, StaffCompensationProfile $profile): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }
        $data = $this->validated($request, $profile->id);
        unset($data['user_id']); // immutable after creation
        $profile->update($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Salary profile updated.']);
    }

    public function destroy(Request $request, StaffCompensationProfile $profile): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('delete_staff_compensation_profiles')) {
            abort(403);
        }
        $profile->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Salary profile removed.']);
    }

    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $query = StaffCompensationProfile::query()->with('user:id,name,email');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Staff', 'Basic', 'Allowances', 'Deductions', 'Annual leave', 'Hire date', 'Active'],
                fn ($p) => [$p->id, $p->user?->name, (float) $p->basic_salary, $p->allowancesTotal(), $p->deductionsTotal(), $p->annual_leave_days, (string) $p->hire_date, $p->is_active ? 'Yes' : 'No'],
                'Salary Profiles',
                app()->getLocale() === 'ar',
            ),
            'salary-profiles-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    protected function validated(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'annual_leave_days' => ['required', 'integer', 'min:0', 'max:90'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.label' => ['required_with:allowances', 'string', 'max:60'],
            'allowances.*.amount' => ['required_with:allowances', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.label' => ['required_with:deductions', 'string', 'max:60'],
            'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Normalise the JSON line arrays (drop blank rows).
        $clean = fn ($rows) => collect($rows ?? [])
            ->map(fn ($r) => ['label' => trim((string) ($r['label'] ?? '')), 'amount' => round((float) ($r['amount'] ?? 0), 3)])
            ->filter(fn ($r) => $r['label'] !== '' && $r['amount'] > 0)
            ->values()->all();

        return [
            'user_id' => $data['user_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'basic_salary' => round((float) $data['basic_salary'], 3),
            'annual_leave_days' => (int) $data['annual_leave_days'],
            'allowances' => $clean($data['allowances'] ?? []),
            'deductions' => $clean($data['deductions'] ?? []),
            'hire_date' => $data['hire_date'] ?? null,
            'termination_date' => $data['termination_date'] ?? null,
            'is_active' => (bool) $request->input('is_active', true),
            'notes' => $data['notes'] ?? null,
        ];
    }

    protected function branchOptions(): array
    {
        return Branch::query()->orderBy('id')->get()
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->localized_name ?? ('Branch '.$b->id)])->all();
    }
}
