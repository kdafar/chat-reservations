<?php

namespace App\Wa\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session; // Import Session facade
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = config('app.fallback_locale', 'en'); // Default locale

        // Check if a locale is set in the session
        if (Session::has('locale')) {
            $sessionLocale = Session::get('locale');
            // Check if the session locale is valid
            if (in_array($sessionLocale, config('app.available_locales', ['en', 'ar']))) { // Get available locales from config if set
                $locale = $sessionLocale;
            }
        }
        // You could add other checks here later, like user preference or browser language

        // Set the application locale for the current request
        App::setLocale($locale);

        // Note: We don't need URL::defaults() or forgetParameter() anymore

        return $next($request);
    }
}
