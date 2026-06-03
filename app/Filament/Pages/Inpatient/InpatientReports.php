<?php

namespace App\Filament\Pages\Inpatient;

use App\Filament\Widgets\Inpatient\AdmissionsByWard;
use App\Filament\Widgets\Inpatient\BedOccupancyTrend;
use App\Filament\Widgets\Inpatient\InpatientKpiStats;
use App\Filament\Widgets\Inpatient\RevenuePerWard;
use Filament\Pages\Page;

class InpatientReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.inpatient.reports';

    protected static ?string $slug = 'inpatient/reports';

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.inpatient');
    }

    public static function getNavigationLabel(): string
    {
        return 'Reports';
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Inpatient reports';
    }

    public static function canAccess(): bool
    {
        // Visible to admins, clinic_admin, and clinic_doctor (so doctors can
        // see the ALOS / occupancy trend). Reception doesn't get this view.
        $u = auth()->user();
        return $u && (
            $u->hasRole(['admin', 'super_admin', 'clinic_admin'])
            || $u->hasAnyPermission(['view_any_admissions'])
        );
    }

    public function getHeaderWidgets(): array
    {
        return [InpatientKpiStats::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }

    public function getWidgets(): array
    {
        return [
            BedOccupancyTrend::class,
            AdmissionsByWard::class,
            RevenuePerWard::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
