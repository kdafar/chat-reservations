<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next)
    {
        // Stored choice wins (set by the /language switcher). On a visitor's
        // very first request we detect their browser language (ar/en) and
        // remember it; otherwise default to Arabic per the house rule.
        $locale = session('lang');

        if (! $locale) {
            $locale = $request->getPreferredLanguage(['ar', 'en']) ?: 'ar';
            session(['lang' => $locale]);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
