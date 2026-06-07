<?php

namespace App\Wa\Providers;

use App\Wa\Events\IncomingWhatsappMessageReceived;
use App\Wa\Events\OutgoingWhatsappMessageSent;
use App\Wa\Events\OutgoingWhatsappStatusReceived;
use App\Wa\Listeners\LogWhatsappMessage;
use App\Wa\Listeners\StoreOutgoingWhatsappMessage;
use App\Wa\Listeners\UpdateWhatsappMessageDelivery;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the isolated WhatsApp module (app/Wa).
 *
 * Responsibilities:
 *  - Inject the module's config into the global namespaces the ported code
 *    reads (services.whatsapp/meta/google, wave, curator) WITHOUT clobbering
 *    any same-named keys the clinic already defines.
 *  - Mount the module's web/api routes under isolated prefixes (/wa, /api/wa).
 *  - Register the module's console commands.
 *
 * The module DB lives on the separate `wa` connection (wam_ table prefix) and
 * its admin UI is the WhatsAppPanelProvider Filament panel. Module migrations
 * are intentionally NOT auto-loaded into the default migrator; run them with:
 *   php artisan migrate --database=wa --path=database/wa_migrations
 */
class WaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/wa_module.php', 'wa_module');

        $cfg = config('wa_module');

        // Clinic-defined keys win on overlap (array_merge: later wins → clinic last).
        config([
            'services.whatsapp' => array_merge($cfg['services_whatsapp'] ?? [], config('services.whatsapp', [])),
            'services.meta' => array_merge($cfg['services_meta'] ?? [], config('services.meta', [])),
            'services.google' => array_merge($cfg['services_google'] ?? [], config('services.google', [])),
            'wave' => array_merge($cfg['wave'] ?? [], config('wave', [])),
            'curator' => array_merge($cfg['curator'] ?? [], config('curator', [])),
        ]);

        // Replicates the source AboutServiceProvider binding.
        $this->app->singleton(
            \App\Wa\Services\About\AboutRepositoryInterface::class,
            \App\Wa\Services\About\DbAboutRepository::class
        );
    }

    public function boot(): void
    {
        // API routes -> /api/wa/*
        Route::middleware('api')
            ->prefix('api/wa')
            ->group(base_path('routes/wa_api.php'));

        // Web routes -> /wa/*
        Route::middleware('web')
            ->prefix('wa')
            ->group(base_path('routes/wa_web.php'));

        // WhatsApp event -> listener wiring (from source EventServiceProvider).
        Event::listen(IncomingWhatsappMessageReceived::class, LogWhatsappMessage::class);
        Event::listen(OutgoingWhatsappMessageSent::class, LogWhatsappMessage::class);
        Event::listen(OutgoingWhatsappMessageSent::class, StoreOutgoingWhatsappMessage::class);
        Event::listen(OutgoingWhatsappStatusReceived::class, UpdateWhatsappMessageDelivery::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Wa\Console\Commands\CreateWhatsappCarouselTemplate::class,
            ]);
        }
    }
}
