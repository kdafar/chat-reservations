<?php

namespace App\Wa\Filament\Widgets;

use Filament\Widgets\TableWidget;

abstract class BasePermissionTableWidget extends TableWidget
{
    protected static ?string $permission = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return static::$permission
            ? $user->can(static::$permission)
            : true;
    }
}
