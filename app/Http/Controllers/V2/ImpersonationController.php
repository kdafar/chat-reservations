<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Log in as another staff user and back. Rules live in ImpersonationService.
 *
 * Both actions answer with a full-page redirect (Inertia::location): the
 * session and CSRF token change underneath the SPA, and every shared prop
 * (roles, permissions, sidebar) has to be rebuilt for the new identity.
 */
class ImpersonationController extends Controller
{
    public function __construct(private ImpersonationService $impersonation) {}

    public function start(Request $request, User $user)
    {
        try {
            $this->impersonation->start($request, $user);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return Inertia::location(route('v2.dashboard'));
    }

    /**
     * Deliberately outside the v2 access middleware: the impersonated account
     * may lose its role or be deactivated mid-session, and the way back must
     * still work.
     */
    public function stop(Request $request)
    {
        if (! $this->impersonation->isImpersonating()) {
            return Inertia::location(route('v2.dashboard'));
        }

        if (! $this->impersonation->stop($request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return Inertia::location(route('filament.admin.auth.login'));
        }

        return Inertia::location(route('v2.users.index'));
    }
}
