<?php

namespace App\Providers\Filament;

use App\Http\Middleware\PartnerContext;
use App\Http\Middleware\SetLocaleFromUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PartnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('partner')
            ->path('partner')
            ->brandName('Partner Portal')
            ->login()
            ->passwordReset()
            ->databaseTransactions()
            ->authGuard('web') // reuse your main 'web' users + pivot partner_user
            ->homeUrl(fn () => \App\Filament\Partner\Pages\OrdersBoard::getUrl())
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->discoverResources(in: app_path('Filament/Partner/Resources'), for: 'App\\Filament\\Partner\\Resources')
            ->discoverPages(in: app_path('Filament/Partner/Pages'), for: 'App\\Filament\\Partner\\Pages')
            ->discoverWidgets(in: app_path('Filament/Partner/Widgets'), for: 'App\\Filament\\Partner\\Widgets')

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                AuthenticateSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                SetLocaleFromUser::class,
                PartnerContext::class,
            ])
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'ar'])
            )
            ->userMenuItems([
                'switch-locale' => \Filament\Navigation\MenuItem::make()
                    ->label(fn () => app()->getLocale() === 'ar'
                        ? __('common.locale.switch_to_english')
                        : __('common.locale.switch_to_arabic'))
                    ->icon('heroicon-o-language')
                    ->url(fn () => route('locale.switch', [
                        'lang' => app()->getLocale() === 'ar' ? 'en' : 'ar',
                    ])),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
