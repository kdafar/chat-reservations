<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends legacy Filament admin pages to their v2 replacement.
 *
 * Runs inside the Filament panel's middleware stack, so it only ever sees the
 * panel's own routes — the v2 Inertia screens are registered separately in
 * routes/web.php and never reach this.
 *
 * Authentication routes are always let through: /admin/login is the only login
 * in the system (see config('clinic.legacy_admin_enabled') for why the panel
 * stays registered), so redirecting it would lock every user out, including
 * from v2.
 */
class RedirectLegacyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('clinic.legacy_admin_enabled')) {
            return $next($request);
        }

        // Login / logout / password reset must keep working.
        $routeName = (string) ($request->route()?->getName() ?? '');
        if (str_starts_with($routeName, 'filament.admin.auth.')) {
            return $next($request);
        }

        // Only intercept real page loads. Livewire/XHR traffic belonging to the
        // login form must pass through untouched, or the form silently breaks.
        if (! $request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return $next($request);
        }

        return redirect()->route('v2.dashboard');
    }
}
