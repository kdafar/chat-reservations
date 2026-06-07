<?php

namespace App\Wa\Filament\Pages\Settings;

use App\Wa\Filament\Pages\BasePermissionPage;

class WhatsAppManagement extends BasePermissionPage
{
    protected static ?string $permission = 'view_whatsapp_management';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'WhatsApp Management';

    protected static ?int $navigationSort = 11;

    // Use the Blade view we’ll create next
    protected static string $view = 'filament.pages.settings.whatsapp-management';

    public function getTitle(): string
    {
        return 'WhatsApp Management (Meta Review Tools)';
    }
}
