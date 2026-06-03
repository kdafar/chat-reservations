<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The clinic shares the *.majestic-kw.com parent domain with a separate
 * Laravel app (fleet.majestic-kw.com). Laravel's default CSRF cookie is named
 * "XSRF-TOKEN" for both apps; fleet scopes its copy to the parent domain
 * (.majestic-kw.com), so the browser ends up holding two same-named cookies on
 * barfres requests. Give this app's CSRF cookie a unique, host-scoped name so
 * the two apps can never collide.
 *
 * Note: the app reads the CSRF token from the <meta name="csrf-token"> tag and
 * sends it as X-CSRF-TOKEN, so it does not depend on the cookie name. Renaming
 * the cookie is purely about isolation and is transparent to the frontend.
 */
class VerifyCsrfToken extends Middleware
{
    protected function newCookie($request, $config)
    {
        return new Cookie(
            'clinic_xsrf_token',
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }
}
