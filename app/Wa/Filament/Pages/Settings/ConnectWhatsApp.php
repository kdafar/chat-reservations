<?php

namespace App\Wa\Filament\Pages\Settings;

use App\Wa\Filament\Pages\BasePermissionPage;

class ConnectWhatsApp extends BasePermissionPage
{
    protected static ?string $permission = 'view_connect_whatsapp';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.settings.connect-whats-app';

    protected static ?string $navigationGroup = 'Settings';
}
