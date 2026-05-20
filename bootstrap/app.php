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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            Halt::class,
        ]);
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
