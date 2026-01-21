<?php

namespace App\Policies\Clinic;

use App\Models\User;

abstract class BaseClinicFilamentPolicy
{
    protected static string $resourceKey;

    public function before(User $user, string $ability): ?bool
    {
        // Keep super_admin/admin always safe (no lockout).
        if (method_exists($user, 'hasRole') && ($user->hasRole('super_admin') || $user->hasRole('admin'))) {
            return true;
        }

        return null;
    }

    protected function can(User $user, string $permission): bool
    {
        return method_exists($user, 'can') ? $user->can($permission) : false;
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'view_any_'.static::$resourceKey);
    }

    public function view(User $user, $model): bool
    {
        return $this->can($user, 'view_'.static::$resourceKey) || $this->can($user, 'view_any_'.static::$resourceKey);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create_'.static::$resourceKey);
    }

    public function update(User $user, $model): bool
    {
        return $this->can($user, 'update_'.static::$resourceKey);
    }

    public function delete(User $user, $model): bool
    {
        return $this->can($user, 'delete_'.static::$resourceKey);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, 'delete_any_'.static::$resourceKey);
    }

    public function forceDelete(User $user, $model): bool
    {
        return $this->can($user, 'force_delete_'.static::$resourceKey);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->can($user, 'force_delete_any_'.static::$resourceKey);
    }

    public function restore(User $user, $model): bool
    {
        return $this->can($user, 'restore_'.static::$resourceKey);
    }

    public function restoreAny(User $user): bool
    {
        return $this->can($user, 'restore_any_'.static::$resourceKey);
    }

    public function reorder(User $user): bool
    {
        return $this->can($user, 'reorder_'.static::$resourceKey);
    }
}
