<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Widgets\BookingFunnel;
use Filament\Pages\Dashboard as BaseDashboard;

class ReservationsDashboard extends BaseDashboard
{
    use HasHelpAction;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    // UI rename only
    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 40;

    protected static ?string $title = null;

    protected static string $routePath = 'clinic-dashboard';

    // Optional: if you want sidebar label different from class name
    protected static ?string $navigationLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.reservations_dashboard.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.reservations_dashboard.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_clinic_reports');
    }

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.reservations_dashboard.what.heading'), 'body' => __('help.pages.reservations_dashboard.what.body')],
            ['heading' => __('help.pages.reservations_dashboard.how.heading'), 'items' => (array) trans('help.pages.reservations_dashboard.how.items')],
            ['heading' => __('help.pages.reservations_dashboard.faq.heading'), 'items' => (array) trans('help.pages.reservations_dashboard.faq.items')],
        ];
    }

    public function getWidgets(): array
    {
        return [
            BookingFunnel::class, // keep your widget (we can rename widget labels later)
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 3,
            '2xl' => 4,
        ];
    }
}
