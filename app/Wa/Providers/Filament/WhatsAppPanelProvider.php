<?php

namespace App\Wa\Providers\Filament;

use App\Wa\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Isolated WhatsApp module admin panel.
 *
 * Registered as an additional Filament panel at /whatsapp/admin, alongside the
 * clinic's Admin/Partner panels. Discovers only the module's own Filament
 * classes (under app/Wa/Filament) and authenticates with the clinic's default
 * web guard so existing staff logins work.
 */
class WhatsAppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $base = dirname(__DIR__, 2).'/Filament'; // app/Wa/Filament

        return $panel
            ->id('whatsapp')
            ->path('whatsapp/admin')
            ->brandName('WhatsApp Admin')
            ->colors([
                'primary' => Color::hex('#25D366'), // WhatsApp green
            ])
            ->sidebarCollapsibleOnDesktop()

            // Dedicated WhatsApp panel classes
            ->discoverResources(in: $base.'/WhatsApp/Resources', for: 'App\\Wa\\Filament\\WhatsApp\\Resources')
            ->discoverPages(in: $base.'/WhatsApp/Pages', for: 'App\\Wa\\Filament\\WhatsApp\\Pages')

            // General module resources/pages/widgets
            ->discoverResources(in: $base.'/Resources', for: 'App\\Wa\\Filament\\Resources')
            ->discoverPages(in: $base.'/Pages', for: 'App\\Wa\\Filament\\Pages')
            ->discoverWidgets(in: $base.'/Widgets', for: 'App\\Wa\\Filament\\Widgets')

            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render(<<<'BLADE'
                    <div class="flex items-center gap-2 mr-4 ml-4">
                        <select
                            onchange="window.location.href = '/language/' + this.value + '?redirect=' + encodeURIComponent(window.location.href)"
                            class="text-gray-900 bg-white border border-gray-300 rounded-lg text-sm px-3 py-1.5 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                        >
                            <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>English</option>
                            <option value="ar" {{ app()->getLocale() === 'ar' ? 'selected' : '' }}> العربية</option>
                        </select>
                    </div>
                BLADE)
            )

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ])
            ->login();
    }
}
