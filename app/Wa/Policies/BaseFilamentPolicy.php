<?php

namespace App\Wa\Policies;

use App\Wa\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BaseFilamentPolicy
{
    use HandlesAuthorization;

    /**
     * The resource name as used in permissions (e.g., 'roles', 'contact_groups').
     * Must be defined in the child class.
     */
    protected static string $resource;

    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('view_any_'.static::$resource);
    }

    public function view(User $user, $model): bool
    {
        return $user->checkPermissionTo('view_'.static::$resource);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('create_'.static::$resource);
    }

    public function update(User $user, $model): bool
    {
        return $user->checkPermissionTo('update_'.static::$resource);
    }

    public function delete(User $user, $model): bool
    {
        return $user->checkPermissionTo('delete_'.static::$resource);
    }

    public function deleteAny(User $user): bool
    {
        return $user->checkPermissionTo('delete_any_'.static::$resource);
    }

    public function restore(User $user, $model): bool
    {
        return $user->checkPermissionTo('restore_'.static::$resource);
    }

    public function restoreAny(User $user): bool
    {
        return $user->checkPermissionTo('restore_any_'.static::$resource);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->checkPermissionTo('force_delete_'.static::$resource);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->checkPermissionTo('force_delete_any_'.static::$resource);
    }

    public function reorder(User $user): bool
    {
        return $user->checkPermissionTo('reorder_'.static::$resource);
    }
}
