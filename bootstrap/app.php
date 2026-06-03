<?php

use App\Console\Commands\PlatformVerify;
use Filament\Support\Exceptions\Halt;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'set-locale' => \App\Http\Middleware\SetLocaleFromSession::class,
            'ensure.customer' => \App\Http\Middleware\EnsureCustomer::class,
            'guest.front' => \App\Http\Middleware\GuestFront::class,
            'ensure.phoneVerified' => \App\Http\Middleware\EnsurePhoneVerified::class, // if you created it
            'partner.context' => \App\Http\Middleware\PartnerContext::class,
            'pos.key' => \App\Http\Middleware\VerifyPosKey::class,
        ]);

        //  always run locale inside the web group
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocaleFromSession::class);

        // Inertia (parallel v2 UI — does not affect Filament/admin or the
        // existing landing). Only active on /v2/* routes, but the middleware
        // is web-wide because Inertia needs it to negotiate the response.
        $middleware->appendToGroup('web', \App\Http\Middleware\HandleInertiaRequests::class);

        // Use the clinic's CSRF middleware so the XSRF cookie gets a unique,
        // host-scoped name (clinic_xsrf_token) instead of the default
        // "XSRF-TOKEN", which collides with the fleet app on the shared
        // *.majestic-kw.com parent domain. See App\Http\Middleware\VerifyCsrfToken.
        // Laravel 12's web group registers ValidateCsrfToken (an alias of
        // VerifyCsrfToken). The CSRF middleware lives in the web GROUP, not the
        // global stack, so it must be swapped with replaceInGroup() (replace()
        // only touches global middleware).
        $middleware->replaceInGroup(
            'web',
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        );

        // The clinic's only login is the Filament admin login. The public
        // customer portal (front /login) is disabled by default
        // (config('clinic.customer_portal_enabled')), so guests hitting any
        // `auth`-gated page — including the admin/v2 Inertia screens — are sent
        // to /admin/login instead of the (possibly non-existent) front login.
        $middleware->redirectGuestsTo(function () {
            return route('filament.admin.auth.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            Halt::class,
        ]);

        // For the v2 Inertia SPA, an unauthenticated request (session expired
        // or logged out) must trigger a clean full-page redirect to the admin
        // login via X-Inertia-Location (HTTP 409). A plain 302 to the login's
        // HTML page makes Inertia render the login form inside a modal overlay
        // on top of the v2 UI instead of actually navigating to it.
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::location(route('filament.admin.auth.login'));
            }
        });
    })
    ->withEvents(discover: [
        app_path('Listeners'),
    ])
    ->withCommands([
        PlatformVerify::class,
        \App\Console\Commands\SendTestMessage::class,
        // add more command classes here
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('audience:refresh')
            ->dailyAt('03:10')
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('clinic:daily-cleanup')->dailyAt('03:00');
        $schedule->command('booking:cleanup-holds')->everyMinute();
        $schedule->command('wa:sessions:expire')->everyFifteenMinutes();
        $schedule->command('tables:free-stuck --hours=6')->dailyAt('03:00');
        $schedule->command('telescope:prune')->dailyAt('00:00');
        $schedule->command('queue:work --queue=campaigns,default --sleep=1 --max-time=55 --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping();
    })
    ->create();
