<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;

class InvestorDashboard extends Page
{
    // 1. Enable Filters (This trait handles the ?filter= logic)
    use HasFiltersForm;

    // 2. Navigation Settings
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Investor Pulse';

    protected static ?string $title = 'Business Health & Financials';

    protected static ?string $navigationGroup = 'Reports';

    // 3. View Location
    protected static string $view = 'filament.pages.investor-dashboard';

    // 4. The Date Filter Form
    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Report Period')
                    ->description('Select the date range for all charts and metrics below.')
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(now()->startOfMonth())
                            ->required()
                            ->maxDate(now()),

                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(now()->endOfMonth())
                            ->required()
                            ->maxDate(now()->endOfMonth()),
                    ])
                    ->columns(2)
                    ->compact(),
            ]);
    }

    /**
     * Fix: Handle the "Refresh Data" button click.
     * This method is required because the view calls wire:submit="update".
     * Livewire automatically syncs the form state before calling this,
     * so we just need to exist to trigger the re-render.
     */
    public function update(): void
    {
        // No logic needed. The render cycle updates the widgets.
    }

    // 5. The Widgets
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\InvestorStatsOverview::class,
            \App\Filament\Widgets\RevenueTrendChart::class,
            \App\Filament\Widgets\DoctorUtilizationChart::class,
        ];
    }
}
