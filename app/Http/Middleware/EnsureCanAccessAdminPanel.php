<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gate the v2 (Inertia) admin routes the same way Filament's admin panel
 * gates itself: authenticated, active status (if the column exists), and
 * at least one role assigned. Mirrors User::canAccessPanel().
 */
class EnsureCanAccessAdminPanel
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (isset($user->status) && $user->status !== null && $user->status !== 'active') {
            abort(403);
        }

        if (method_exists($user, 'roles') && ! $user->roles()->exists()) {
            abort(403);
        }

        return $next($request);
    }
}
