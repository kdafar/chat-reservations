<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerContext
{
    public function handle(Request $request, Closure $next)
    {
        // Only enforce on Partner panel paths
        if (! str_starts_with($request->path(), 'partner')) {
            return $next($request);
        }

        // Identify Filament Partner auth routes (login, password reset, etc.)
        $isAuthRoute = $request->routeIs('filament.partner.auth.*')
            // Fallback safety if route names differ:
            || $request->is('partner/login')
            || $request->is('partner/password-reset')
            || $request->is('partner/password-request');

        $user = Auth::guard('web')->user();

        // If it's an auth route, never redirect back to /partner/login
        if ($isAuthRoute) {
            // If a signed-in user reaches the login page but has no partner, log them out.
            if ($user) {
                $hasPartner = $user->partners()->exists();
                if (! $hasPartner) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }

            return $next($request);
        }

        // Non-auth Partner panel routes:
        if (! $user) {
            // Not logged in → let Filament auth middleware send them to login
            return $next($request);
        }

        // Must be linked to at least one partner
        $partnerIds = $user->partners()->pluck('partners.id')->all();
        if (empty($partnerIds)) {
            // Redirect ONLY from non-auth routes
            return redirect()->to('/partner/login')
                ->withErrors(['email' => __('You are not linked to any partner.')]);
        }

        // Handle active partner switching via ?partner_id=... or default to first
        if ($requested = $request->query('partner_id')) {
            $requested = (int) $requested;
            if (in_array($requested, $partnerIds, true)) {
                session(['active_partner_id' => $requested]);
            }
        }

        if (! session()->has('active_partner_id')) {
            session(['active_partner_id' => (int) reset($partnerIds)]);
        }

        return $next($request);
    }
}
