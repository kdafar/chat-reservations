<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ErrorContext
{
    /**
     * Determine if the current request is within an administrative context.
     */
    public static function isAdmin(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        // 1. Check Filament Panel Paths (V3)
        if (class_exists('\Filament\Facades\Filament')) {
            try {
                $panels = \Filament\Facades\Filament::getPanels();
                foreach ($panels as $panel) {
                    $panelPath = trim($panel->getPath(), '/');
                    if ($panelPath !== '' && self::startsWith($path, $panelPath)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Fail silently to avoid breaking the error page itself
            }
        }

        // 2. Check Config Fallback
        $filamentPath = (string) config('filament.path', 'admin');
        $filamentPath = trim($filamentPath, '/');

        if ($filamentPath !== '' && self::startsWith($path, $filamentPath)) {
            return true;
        }

        // 3. Common industry standard prefixes
        return self::startsWith($path, 'admin')
            || self::startsWith($path, 'dashboard')
            || self::startsWith($path, 'filament')
            || self::startsWith($path, 'cp');
    }

    /**
     * Get the appropriate home URL based on the user's context.
     */
    public static function homeUrl(Request $request): string
    {
        if (self::isAdmin($request)) {
            // Priority 1: Current Filament Panel Dashboard
            if (class_exists('\Filament\Facades\Filament')) {
                try {
                    $currentPanel = \Filament\Facades\Filament::getCurrentPanel();
                    if ($currentPanel) {
                        return $currentPanel->getUrl();
                    }

                    // Fallback to default admin panel route
                    if (Route::has('filament.admin.pages.dashboard')) {
                        return route('filament.admin.pages.dashboard');
                    }
                } catch (\Throwable $e) {
                }
            }

            // Priority 2: Config path
            $filamentPath = (string) config('filament.path', 'admin');

            return url('/'.ltrim($filamentPath, '/'));
        }

        // Standard User Home
        return url('/');
    }

    /**
     * String helper to avoid dependency on Str for basic logic if needed,
     * though we use Str::startsWith where possible.
     */
    private static function startsWith(string $haystack, string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }

        return strncmp($haystack, $prefix, strlen($prefix)) === 0;
    }
}
