<?php

namespace App\Filament\Partner\Pages;

use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Partner Dashboard';

    protected static string $view = 'filament.partner.pages.dashboard';

    // Optional: put it first in the nav
    protected static ?int $navigationSort = 0;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
