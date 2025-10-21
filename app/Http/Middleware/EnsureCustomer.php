<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

class EnsureCustomer
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            session()->put('url.intended', $request->fullUrl());
            $login = route()->has('login') ? route('login') : url('/login');

            return redirect()->to($login);
        }

        $user = auth()->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Email not verified.'], 403);
            }
            session()->put('url.intended', $request->fullUrl());

            return redirect()->route('verification.notice')
                ->with('status', __('Please verify your email to continue.'));
        }

        // Prefer spatie/permission if present
        if (method_exists($user, 'hasRole')) {
            if (! $user->hasAnyRole(['customer', 'Customer'])) {
                return $this->forbidden($request);
            }
        }

        if (property_exists($user, 'status') && method_exists($user, 'isActive') && ! $user->isActive()) {
            return $this->forbidden($request);
        }
        // Fallback to 'role' string column
        elseif (isset($user->role) && ! in_array(strtolower($user->role), ['customer', 'user'])) {
            return $this->forbidden($request);
        }

        return $next($request);
    }

    protected function forbidden(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return redirect()->to(url('/'))->with('error', __('You are not allowed to access this page.'));
    }
}
