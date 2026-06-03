<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class GuestFront
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            // If already logged in, kick to home (or intended)
            $home = Route::has('home') ? route('home') : url('/');

            return redirect()->intended($home);
        }

        return $next($request);
    }
}
