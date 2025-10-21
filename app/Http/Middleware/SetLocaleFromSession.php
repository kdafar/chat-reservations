<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale(session('lang', 'ar')); // default Arabic per your rule

        return $next($request);
    }
}
