<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GuestFront
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            // If already logged in, kick to home (or intended)
            $home = route()->has('home') ? route('home') : url('/');

            return redirect()->intended($home);
        }

        return $next($request);
    }
}
