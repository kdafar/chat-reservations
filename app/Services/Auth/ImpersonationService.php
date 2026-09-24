<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Log in as" another staff account, for support: see exactly what a doctor
 * or receptionist sees, reproduce their problem, then return.
 *
 * Rules:
 *  - only a global admin (admin / super_admin) may impersonate — never a
 *    branch-scoped clinic_admin, who would otherwise escape their scope;
 *  - never another global admin (no peer hijack / privilege laundering);
 *  - only an active account with a role (anything else can't open v2, and
 *    the admin would land on a 403);
 *  - no nesting: while impersonating you can't impersonate again.
 *
 * The admin's id rides in the session. Start and stop are written to the
 * activity log, and every activity row recorded meanwhile is tagged with
 * `impersonated_by` (see AppServiceProvider) so audits show who really acted.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public const GLOBAL_ADMIN_ROLES = ['admin', 'super_admin'];

    /** Why $admin may not impersonate $target, or null when allowed. */
    public function denialReason(?User $admin, User $target): ?string
    {
        if (! $admin || ! $admin->hasAnyRole(self::GLOBAL_ADMIN_ROLES)) {
            return 'Only an administrator can log in as another user.';
        }
        if ($this->isImpersonating()) {
            return 'Return to your own account first.';
        }
        if ((int) $admin->id === (int) $target->id) {
            return 'You are already logged in as yourself.';
        }
        if ($target->hasAnyRole(self::GLOBAL_ADMIN_ROLES)) {
            return 'Administrator accounts cannot be impersonated.';
        }
        if (($target->status ?? 'active') !== 'active') {
            return 'This account is inactive.';
        }
        if ($target->roles->isEmpty()) {
            return 'This account has no role, so it cannot open the admin.';
        }

        return null;
    }

    public function canImpersonate(?User $admin, User $target): bool
    {
        return $this->denialReason($admin, $target) === null;
    }

    public function start(Request $request, User $target): void
    {
        /** @var User $admin */
        $admin = $request->user();
        if ($reason = $this->denialReason($admin, $target)) {
            throw new \RuntimeException($reason);
        }

        activity('auth')
            ->causedBy($admin)
            ->performedOn($target)
            ->event('impersonation_started')
            ->withProperties(['ip' => $request->ip()])
            ->log("{$admin->name} started impersonating {$target->name}");

        // Login first, then stash the admin: Auth::login() migrates the
        // session id, and the key must survive into the new session.
        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, (int) $admin->id);
    }

    /**
     * Return to the admin's own account. Returns false when there is no
     * (usable) admin to return to — the caller then logs out entirely.
     */
    public function stop(Request $request): bool
    {
        $adminId = (int) $request->session()->get(self::SESSION_KEY, 0);
        $target = $request->user();
        $request->session()->forget(self::SESSION_KEY);

        $admin = $adminId ? User::query()->find($adminId) : null;
        // The admin may have been demoted or disabled meanwhile — don't hand
        // the session back to an account that could no longer log in.
        if (! $admin || ! $admin->hasAnyRole(self::GLOBAL_ADMIN_ROLES) || ($admin->status ?? 'active') !== 'active') {
            return false;
        }

        activity('auth')
            ->causedBy($admin)
            ->when($target, fn ($a) => $a->performedOn($target))
            ->event('impersonation_ended')
            ->withProperties(['ip' => $request->ip()])
            ->log("{$admin->name} stopped impersonating ".($target?->name ?? 'a user'));

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return true;
    }

    public function isImpersonating(): bool
    {
        return app()->bound('session') && (int) session(self::SESSION_KEY, 0) > 0;
    }

    public function impersonator(): ?User
    {
        return $this->isImpersonating() ? User::query()->find((int) session(self::SESSION_KEY)) : null;
    }
}
