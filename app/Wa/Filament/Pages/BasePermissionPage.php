<?php

namespace App\Wa\Filament\Pages;

use Filament\Pages\Page;

abstract class BasePermissionPage extends Page
{
    /**
     * Example: 'view_settings', 'view_media', 'view_system_cache'
     * Put the EXACT permission name here (Spatie permission).
     */
    protected static ?string $permission = null;

    public static function canAccess(): bool
    {
        $permission = static::$permission;

        // If no permission configured, deny by default (safer)
        if (! $permission) {
            return false;
        }

        return auth()->check() && auth()->user()->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
