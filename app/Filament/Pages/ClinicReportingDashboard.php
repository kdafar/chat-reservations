<?php

namespace App\Filament\Pages;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Dashboard as FilamentDashboard;

class ClinicReportingDashboard extends FilamentDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Clinic — Reports';

    protected static ?string $navigationLabel = 'Clinic Reports';

    protected static ?string $slug = 'clinic-reports';

    protected static ?int $navigationSort = 10;

    // public static function canAccess(): bool
    // {
    //     return auth()->user()->can('view_clinic_reports');
    // }

    /**
     * IMPORTANT:
     * In your Filament build, dashboard filters are rendered via Infolists (not Forms),
     * so defining filtersForm(Form $form) will crash.
     *
     * This method prevents the fatal error and keeps the page working.
     */
    public function filtersInfolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Grid::make(1)->schema([
                TextEntry::make('clinic_reports_note')
                    ->label('')
                    ->state('Filters UI is not available on this Dashboard build. Use the widgets below (snapshot-only).')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\Clinic\ClinicProfitOverview::class,
            \App\Filament\Widgets\Clinic\ClinicProfitTrend::class,
            \App\Filament\Widgets\Clinic\ClinicMarginTrend::class,
            \App\Filament\Widgets\Clinic\ClinicDoctorCutTrend::class,
            \App\Filament\Widgets\Clinic\ClinicTopDoctors::class,
            \App\Filament\Widgets\Clinic\ClinicTopItems::class,
        ];
    }
}
