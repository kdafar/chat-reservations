<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\PartnerPanelProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    // Isolated WhatsApp module (app/Wa)
    App\Wa\Providers\WaServiceProvider::class,
    App\Wa\Providers\Filament\WhatsAppPanelProvider::class,
];
