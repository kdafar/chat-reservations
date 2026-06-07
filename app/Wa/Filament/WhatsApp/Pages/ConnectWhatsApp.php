<?php

namespace App\Wa\Filament\WhatsApp\Pages;

use Filament\Pages\Page;

class ConnectWhatsApp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Connect WhatsApp';

    protected static ?string $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.whatsapp.pages.connect-whatsapp';

    public function getTitle(): string
    {
        return 'Connect WhatsApp (Embedded Signup)';
    }

    public static function canAccess(): bool
    {
        // adjust if you want extra restriction
        return auth()->check();
    }
}
