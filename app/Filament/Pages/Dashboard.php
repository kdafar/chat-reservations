<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\WhatsAppStatusWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.dashboard';

    protected static ?string $slug = 'dashboard';

    public function getHeaderWidgets(): array
    {
        return [
            WhatsAppStatusWidget::class,
        ];
    }

    public function hasLogo(): bool
    {
        return false; // Set to false to hide the logo and fix the error
    }

    public function getLogo(): ?string
    {
        return null;
    }

    public function getLogoHeight(): ?string
    {
        return null;
    }
}
