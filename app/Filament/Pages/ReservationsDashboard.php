<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BookingFunnel;
use Filament\Pages\Dashboard as BaseDashboard;

class ReservationsDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    // UI rename only
    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Clinic Dashboard';

    protected static string $routePath = 'clinic-dashboard';

    // Optional: if you want sidebar label different from class name
    protected static ?string $navigationLabel = 'Clinic Dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_clinic_reports');
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
