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
        $query = User::query()->with('roles:id,name');
        if ($q !== '') { $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")); }
        if ($role !== '') { $query->whereHas('roles', fn ($r) => $r->where('name', $role)); }
        if (in_array($status, ['active', 'inactive'], true)) { $query->where('status', $status); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('name'),
                ['ID', 'Name', 'Email', 'Phone', 'Roles', 'Status'],
                fn ($u) => [$u->id, $u->name, $u->email, $u->phone, $u->roles->pluck('name')->implode(', '), $u->status],
                'Users',
                app()->getLocale() === 'ar',
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
        ];

        $query = User::query()->with('roles:id,name');

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

        $page = $query->orderBy('name')->paginate(25)->withQueryString();

        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all();

        $counts = [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', 'active')->count(),
            'inactive' => User::query()->where('status', '!=', 'active')->count(),
        ];

        return Inertia::render('Users/Index', [
            'filters' => $filters,
            'page' => $page,
            'roles' => $roles,
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
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles'] ?? []);

        // Role assignment is a pivot write (no model event); audit it explicitly.
        $roles = $user->getRoleNames()->sort()->values()->all();
        if ($roles) {
            activity('user')
                ->performedOn($user)
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties(['attributes' => ['roles' => $roles], 'old' => ['roles' => []]])
                ->log("Roles assigned to {$user->name}");
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
        $user->update($update);
        $user->syncRoles($data['roles'] ?? []);

        // The user-attribute changes log via the model trait; role changes are a
        // pivot write, so diff and audit them separately.
        $newRoles = $user->getRoleNames()->sort()->values()->all();
        if ($oldRoles !== $newRoles) {
            activity('user')
                ->performedOn($user)
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties(['attributes' => ['roles' => $newRoles], 'old' => ['roles' => $oldRoles]])
                ->log("Roles updated for {$user->name}");
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'User updated.']);
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
