<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasHelpAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Dashboard as FilamentDashboard;

class ClinicReportingDashboard extends FilamentDashboard
{
    use HasHelpAction;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'clinic-reports';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.clinic_reporting_dashboard.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.clinic_reporting_dashboard.title');
    }

    // public static function canAccess(): bool
    // {
    //     return auth()->user()->can('view_clinic_reports');
    // }

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.clinic_reporting_dashboard.what.heading'), 'body' => __('help.pages.clinic_reporting_dashboard.what.body')],
            ['heading' => __('help.pages.clinic_reporting_dashboard.how.heading'), 'items' => (array) trans('help.pages.clinic_reporting_dashboard.how.items')],
            ['heading' => __('help.pages.clinic_reporting_dashboard.faq.heading'), 'items' => (array) trans('help.pages.clinic_reporting_dashboard.faq.items')],
        ];
    }

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
