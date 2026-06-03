<?php

namespace App\Filament\Widgets\Inpatient;

use App\Models\Inpatient\Admission;
use App\Models\Inpatient\Bed;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BedOccupancyWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    /**
     * Only show on the clinic dashboard if there's at least one ward.
     * Avoids cluttering panels for clinics that aren't using inpatient.
     */
    public static function canView(): bool
    {
        return \App\Models\Inpatient\Ward::query()->exists();
    }

    protected function getStats(): array
    {
        $bedsQ = Bed::query()->where('is_active', true);
        $total = (int) $bedsQ->clone()->count();
        $occupied = (int) $bedsQ->clone()->where('status', Bed::STATUS_OCCUPIED)->count();
        $available = (int) $bedsQ->clone()->where('status', Bed::STATUS_AVAILABLE)->count();
        $cleaning = (int) $bedsQ->clone()->where('status', Bed::STATUS_CLEANING)->count();

        $occupancyPct = $total > 0 ? round(($occupied / $total) * 100) : 0;
        $activeAdmissions = (int) Admission::query()->where('status', Admission::STATUS_ACTIVE)->count();

        return [
            Stat::make('Occupancy', "{$occupancyPct}%")
                ->description("{$occupied} of {$total} beds occupied")
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color($occupancyPct >= 90 ? 'danger' : ($occupancyPct >= 70 ? 'warning' : 'success')),

            Stat::make('Active admissions', $activeAdmissions)
                ->description('Currently in beds or awaiting assignment')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('warning'),

            Stat::make('Available beds', $available)
                ->description("{$cleaning} beds being cleaned")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
