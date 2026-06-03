<?php

namespace App\Http\Controllers\Front\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LogoutController extends Controller
{
    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The v2 admin SPA logs out via an Inertia request (router.post('/logout')).
        // A normal 302 makes Inertia render the target page inside a modal overlay
        // instead of navigating to it, so force a clean full-page redirect to the
        // admin login screen via X-Inertia-Location (HTTP 409).
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('filament.admin.auth.login'));
        }

        return redirect()->route('home');
    }
}
