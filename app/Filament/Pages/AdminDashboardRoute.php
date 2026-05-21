<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class AdminDashboardRoute extends Page
{
    protected static ?string $slug = 'dashboard';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.blank';

    public function mount(): void
    {
        $user = auth()->user();

        // If not logged in for any reason, fall back to Filament login
        if (! $user) {
            $this->redirect(Filament::getLoginUrl());

            return;
        }

        if ($user->hasRole('doctor') === true) {
            $this->redirect(route('filament.admin.pages.waiting-patients'));

            return;
        }

        // Admin -> Executive Dashboard (only if they can access it)
        // (Keeps permission logic centralized in ExecutiveDashboard::canAccess())
        if (\App\Filament\Pages\ExecutiveDashboard::canAccess()) {
            $this->redirect(\App\Filament\Pages\ExecutiveDashboard::getUrl());

            return;
        }

        // Fallback: your current default landing page
        $this->redirect(route('filament.admin.pages.clinic-reports'));
    }
}
