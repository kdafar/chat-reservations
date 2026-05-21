<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale for the current request.
 *
 * Resolution order (first non-empty wins):
 *   1. ?lang=ar query string (one-shot override; also saved to user when authenticated)
 *   2. session('locale') (set by the locale-switcher action)
 *   3. authenticated user's preferred_locale column
 *   4. config('app.locale') fallback
 *
 * Supported values: 'en', 'ar'. Anything else falls back to default.
 */
class SetLocaleFromUser
{
    private const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        // Localize Carbon so diffForHumans() ("2 hours ago" → "منذ ساعتين")
        // and date formatting respect the same locale.
        Carbon::setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $candidate = $request->query('lang')
            ?? session('locale')
            ?? $request->user()?->preferred_locale
            ?? config('app.locale');

        $candidate = is_string($candidate) ? strtolower($candidate) : 'en';

        return in_array($candidate, self::SUPPORTED, true)
            ? $candidate
            : (string) config('app.locale', 'en');
    }
}
