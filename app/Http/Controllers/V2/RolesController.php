<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles & Permissions — v2 replacement for Filament RoleResource.
 *
 * Admin-only. The big win over the Filament version is the permission picker:
 * instead of one flat 700-item checkbox list, permissions are grouped by the
 * resource they govern (derived from the permission name) so an admin can scan,
 * search, and bulk-toggle a whole group at once.
 */
class RolesController extends Controller
{
    /** Roles that must never be deleted/renamed — they are wired into code & gates. */
    protected const PROTECTED_ROLES = ['admin', 'super_admin'];

    /** Leading action verbs Filament Shield emits. Longest-first so `view_any` beats `view`. */
    protected const ACTIONS = [
        'view_any', 'force_delete_any', 'force_delete', 'delete_any', 'restore_any',
        'view', 'create', 'update', 'delete', 'restore', 'replicate', 'reorder', 'manage',
    ];

    protected function authorizeAccess(Request $request): void
    {
        $u = $request->user();
        if (! $u || ! $u->can('roles.view-any')) {
            abort(403, 'Only admins can manage roles.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $q = trim((string) $request->input('q', ''));

        $rolesQuery = Role::query()
            ->where('guard_name', 'web')
            ->withCount('permissions')
            ->with('permissions:id,name');

        if ($q !== '') {
            $rolesQuery->where('name', 'like', "%{$q}%");
        }

        $roles = $rolesQuery->orderBy('name')->get()->map(fn (Role $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'guard_name' => $r->guard_name,
            'permissions_count' => $r->permissions_count,
            'permissions' => $r->permissions->pluck('name')->all(),
            'protected' => in_array($r->name, self::PROTECTED_ROLES, true),
            'users_count' => $r->users()->count(),
        ])->all();

        return Inertia::render('Roles/Index', [
            'filters' => ['q' => $q],
            'roles' => $roles,
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        // Permission sync is a pivot write (no model event), so audit it explicitly.
        activity('role')
            ->performedOn($role)
            ->causedBy($request->user())
            ->event('created')
            ->withProperties(['attributes' => [
                'name' => $role->name,
                'permissions' => $role->getPermissionNames()->sort()->values()->all(),
            ]])
            ->log("Role created: {$role->name}");

        return back()->with('flash', ['type' => 'success', 'message' => 'Role created.']);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeAccess($request);

        $isProtected = in_array($role->name, self::PROTECTED_ROLES, true);

        $data = $request->validate([
            // Protected roles keep their name; everything else may be renamed.
            'name' => ['required', 'string', 'max:191', Rule::unique('roles', 'name')->ignore($role->id)->where('guard_name', 'web')],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $oldName = $role->name;
        $oldPerms = $role->getPermissionNames()->sort()->values()->all();

        if (! $isProtected) {
            $role->update(['name' => $data['name']]);
        }

        $role->syncPermissions($data['permissions'] ?? []);

        // Diff name + permissions and audit only if something actually changed.
        $newPerms = $role->getPermissionNames()->sort()->values()->all();
        $attributes = [];
        $old = [];
        if ($oldName !== $role->name) { $attributes['name'] = $role->name; $old['name'] = $oldName; }
        if ($oldPerms !== $newPerms) { $attributes['permissions'] = $newPerms; $old['permissions'] = $oldPerms; }
        if ($attributes) {
            activity('role')
                ->performedOn($role)
                ->causedBy($request->user())
                ->event('updated')
                ->withProperties(['attributes' => $attributes, 'old' => $old])
                ->log("Role updated: {$role->name}");
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Role updated.']);
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeAccess($request);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->with('flash', ['type' => 'error', 'message' => "The {$role->name} role is protected and can't be deleted."]);
        }
        if ($role->users()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'This role is still assigned to users. Reassign them first.']);
        }

        $snapshot = [
            'name' => $role->name,
            'permissions' => $role->getPermissionNames()->sort()->values()->all(),
        ];
        $role->delete();

        activity('role')
            ->performedOn($role)
            ->causedBy($request->user())
            ->event('deleted')
            ->withProperties(['old' => $snapshot])
            ->log("Role deleted: {$snapshot['name']}");

        return back()->with('flash', ['type' => 'success', 'message' => 'Role deleted.']);
    }

    /**
     * Group all web permissions by the resource they govern, derived from the
     * permission name. Handles both Shield formats:
     *   - underscored: `create_accounting_vendors` → group "accounting vendors", action "create"
     *   - dotted:      `branches.view-any`          → group "branches", action "view any"
     *
     * @return array<int, array{key:string, label:string, permissions: array<int, array{name:string, action:string}>}>
     */
    protected function groupedPermissions(): array
    {
        $perms = Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name');

        $groups = [];
        foreach ($perms as $name) {
            [$group, $action] = $this->splitPermission($name);
            $groups[$group] ??= [];
            $groups[$group][] = ['name' => $name, 'action' => $action];
        }

        ksort($groups);

        return collect($groups)->map(fn ($items, $key) => [
            'key' => $key,
            'label' => Str::of($key)->replace(['_', '-', '.'], ' ')->title()->toString(),
            'permissions' => $items,
        ])->values()->all();
    }

    /** @return array{0:string,1:string} [group, action] */
    protected function splitPermission(string $name): array
    {
        // Dotted form: branches.view-any
        if (str_contains($name, '.')) {
            [$group, $action] = explode('.', $name, 2);

            return [$group, str_replace('-', ' ', $action)];
        }

        // Underscored form: strip a leading action verb if present.
        foreach (self::ACTIONS as $action) {
            if (str_starts_with($name, $action.'_')) {
                return [substr($name, strlen($action) + 1), str_replace('_', ' ', $action)];
            }
            if ($name === $action) {
                return ['general', str_replace('_', ' ', $action)];
            }
        }

        // No recognised verb — bucket under the first token.
        $parts = explode('_', $name, 2);

        return [$parts[1] ?? $name, $parts[0]];
    }
}
