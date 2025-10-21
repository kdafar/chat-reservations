<?php

use App\Console\Commands\PlatformVerify;
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
        ]);

        //  always run locale inside the web group
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocaleFromSession::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withEvents(discover: [
        app_path('Listeners'),
    ])
    ->withCommands([
        PlatformVerify::class,
        \App\Console\Commands\SendTestMessage::class,
        // add more command classes here
    ])->create();
