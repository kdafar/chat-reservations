<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Users — v2 replacement for Filament UserResource.
 *
 * Admin-only. Role assignment + branch assignment happen on the same form.
 */
class UsersController extends Controller
{
    use \App\Support\ResolvesAccessibleClinics;

    protected function authorizeAccess(Request $request): void
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_any_user')) {
            abort(403, 'Only admins can manage users.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $role = $request->input('role', '');
        $status = $request->input('status', 'all');
        $branch = (int) $request->input('branch', 0);
        $query = User::query()->with(['roles:id,name', 'branchLinks:id,name']);
        if ($q !== '') { $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")); }
        if ($role !== '') { $query->whereHas('roles', fn ($r) => $r->where('name', $role)); }
        if (in_array($status, ['active', 'inactive'], true)) { $query->where('status', $status); }
        if ($branch > 0) { $query->whereHas('branchLinks', fn ($b) => $b->where('branches.id', $branch)); }
        $locale = app()->getLocale();
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('name'),
                ['ID', 'Name', 'Email', 'Phone', 'Roles', 'Branches', 'Status'],
                fn ($u) => [
                    $u->id, $u->name, $u->email, $u->phone,
                    $u->roles->pluck('name')->implode(', '),
                    $u->branchLinks->map(fn ($b) => $b->getTranslation('name', $locale, true))->implode(', '),
                    $u->status,
                ],
                'Users',
                $locale === 'ar',
            ),
            'users-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'role' => $request->input('role', ''),
            'status' => $request->input('status', 'all'),
            'branch' => (int) $request->input('branch', 0) ?: '',
        ];

        $query = User::query()->with(['roles:id,name', 'branchLinks:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }
        if ($filters['role'] !== '') {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
        }
        if ($filters['status'] === 'active') {
            $query->where('status', 'active');
        } elseif ($filters['status'] === 'inactive') {
            $query->where('status', '!=', 'active');
        }
        if ($filters['branch'] !== '') {
            $query->whereHas('branchLinks', fn ($b) => $b->where('branches.id', $filters['branch']));
        }

        $locale = app()->getLocale();

        // Branch names are translatable, so resolve them here rather than
        // leaking the raw JSON column into the page props.
        $page = $query->orderBy('name')->paginate(25)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status,
                'roles' => $u->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
                'branches' => $u->branchLinks
                    ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->getTranslation('name', $locale, true)])
                    ->values(),
            ]);

        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all();

        $counts = [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', 'active')->count(),
            'inactive' => User::query()->where('status', '!=', 'active')->count(),
            'unassigned' => User::query()->whereDoesntHave('branchLinks')->count(),
        ];

        return Inertia::render('Users/Index', [
            'filters' => $filters,
            'page' => $page,
            'roles' => $roles,
            'branches' => $this->accessibleBranches()->all(),
            'counts' => $counts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::in($this->assignableBranchIds())],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles'] ?? []);
        $user->branchLinks()->sync($data['branches'] ?? []);

        // Role/branch assignment is a pivot write (no model event); audit it explicitly.
        $roles = $user->getRoleNames()->sort()->values()->all();
        $branches = $this->branchNamesFor($user);
        if ($roles || $branches) {
            activity('user')
                ->performedOn($user)
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties([
                    'attributes' => ['roles' => $roles, 'branches' => $branches],
                    'old' => ['roles' => [], 'branches' => []],
                ])
                ->log("Roles and branches assigned to {$user->name}");
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'User created.']);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::in($this->assignableBranchIds())],
        ]);

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ];
        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        $oldRoles = $user->getRoleNames()->sort()->values()->all();
        $oldBranches = $this->branchNamesFor($user);
        $user->update($update);
        $user->syncRoles($data['roles'] ?? []);

        if (array_key_exists('branches', $data)) {
            $user->branchLinks()->sync($this->withDoctorBranch($user, $data['branches']));
            $user->unsetRelation('branchLinks');
        }

        // The user-attribute changes log via the model trait; role and branch
        // changes are pivot writes, so diff and audit them separately.
        $newRoles = $user->getRoleNames()->sort()->values()->all();
        $newBranches = $this->branchNamesFor($user);
        if ($oldRoles !== $newRoles || $oldBranches !== $newBranches) {
            activity('user')
                ->performedOn($user)
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties([
                    'attributes' => ['roles' => $newRoles, 'branches' => $newBranches],
                    'old' => ['roles' => $oldRoles, 'branches' => $oldBranches],
                ])
                ->log("Roles and branches updated for {$user->name}");
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'User updated.']);
    }

    /**
     * Bulk branch assignment for the checked rows: add to, remove from, or
     * replace each user's branch scope in one write.
     */
    public function bulkBranches(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'mode' => ['required', 'string', 'in:add,remove,replace'],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::in($this->assignableBranchIds())],
        ]);

        $branchIds = array_values(array_unique(array_map('intval', $data['branches'] ?? [])));
        if ($branchIds === [] && $data['mode'] !== 'replace') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Pick at least one branch.']);
        }

        $users = User::query()->with('branchLinks:id,name')->whereIn('id', $data['user_ids'])->get();
        $changed = 0;

        foreach ($users as $user) {
            $oldBranches = $this->branchNamesFor($user);

            match ($data['mode']) {
                'add' => $user->branchLinks()->syncWithoutDetaching($branchIds),
                'remove' => $user->branchLinks()->detach($this->withoutDoctorBranch($user, $branchIds)),
                'replace' => $user->branchLinks()->sync($this->withDoctorBranch($user, $branchIds)),
            };

            $user->unsetRelation('branchLinks');
            $newBranches = $this->branchNamesFor($user);
            if ($oldBranches === $newBranches) {
                continue;
            }
            $changed++;

            activity('user')
                ->performedOn($user)
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties([
                    'attributes' => ['branches' => $newBranches],
                    'old' => ['branches' => $oldBranches],
                ])
                ->log("Branches updated for {$user->name} (bulk)");
        }

        return back()->with('flash', [
            'type' => 'success',
            'message' => $changed === 0
                ? 'No branch changes were needed.'
                : "Branches updated for {$changed} user(s).",
        ]);
    }

    /** Branch ids the acting admin may hand out (all of them for a global admin). */
    protected function assignableBranchIds(): array
    {
        return $this->accessibleBranches()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Sorted, localized branch names — the shape the activity log stores. */
    protected function branchNamesFor(User $user): array
    {
        $locale = app()->getLocale();

        return $user->branchLinks()->get(['branches.id', 'branches.name'])
            ->map(fn (Branch $b) => $b->getTranslation('name', $locale, true))
            ->sort()->values()->all();
    }

    /**
     * A doctor's own branch is mirrored into `branch_user` by DoctorObserver and
     * everything branch-scoped relies on it, so never let an edit strip it.
     */
    protected function withDoctorBranch(User $user, array $branchIds): array
    {
        $branchIds = array_map('intval', $branchIds);
        $doctorBranchId = (int) ($user->doctorProfile()->value('branch_id') ?? 0);
        if ($doctorBranchId > 0) {
            $branchIds[] = $doctorBranchId;
        }

        return array_values(array_unique($branchIds));
    }

    /** The inverse guard: bulk-remove must not strip a doctor's own branch. */
    protected function withoutDoctorBranch(User $user, array $branchIds): array
    {
        $doctorBranchId = (int) ($user->doctorProfile()->value('branch_id') ?? 0);

        return array_values(array_filter(
            array_map('intval', $branchIds),
            fn (int $id) => $id !== $doctorBranchId,
        ));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAccess($request);
        if ($user->id === $request->user()->id) {
            return back()->with('flash', ['type' => 'error', 'message' => "Can't delete yourself."]);
        }
        $user->update(['status' => 'inactive']);
        return back()->with('flash', ['type' => 'success', 'message' => 'User deactivated.']);
    }
}
