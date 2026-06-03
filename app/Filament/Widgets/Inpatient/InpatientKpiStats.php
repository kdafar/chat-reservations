<?php

namespace App\Filament\Widgets\Inpatient;

use App\Models\Inpatient\Admission;
use App\Models\Inpatient\AdmissionCharge;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI strip for the inpatient reports page:
 *   - Average length of stay (last 30 days of discharges)
 *   - Total admissions this month
 *   - Bed-day revenue this month
 *   - Active admissions right now
 */
class InpatientKpiStats extends BaseWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return \App\Models\Inpatient\Ward::query()->exists();
    }

    protected function getStats(): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $thirtyAgo = now($tz)->subDays(30);
        $monthStart = now($tz)->startOfMonth();

        // ALOS over the last 30 days of discharges.
        $discharged = Admission::query()
            ->whereNotNull('discharged_at')
            ->where('discharged_at', '>=', $thirtyAgo)
            ->get(['admitted_at', 'discharged_at']);

        $alos = 0.0;
        if ($discharged->isNotEmpty()) {
            $total = $discharged->reduce(function (float $carry, $a) {
                return $carry + ($a->admitted_at && $a->discharged_at
                    ? $a->admitted_at->diffInDays($a->discharged_at, true)
                    : 0);
            }, 0.0);
            $alos = round($total / $discharged->count(), 1);
        }

        $admittedThisMonth = (int) Admission::query()
            ->where('admitted_at', '>=', $monthStart)
            ->count();

        $bedRevenueThisMonth = (float) AdmissionCharge::query()
            ->where('charge_date', '>=', $monthStart->toDateString())
            ->where('source', AdmissionCharge::SOURCE_BED_DAY)
            ->sum('amount');

        $activeNow = (int) Admission::query()->where('status', Admission::STATUS_ACTIVE)->count();

        return [
            Stat::make('ALOS (30d)', $alos.' d')
                ->description($discharged->count().' discharges in last 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),

            Stat::make('Admissions this month', $admittedThisMonth)
                ->description('from '.$monthStart->format('M j'))
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('success'),

            Stat::make('Bed revenue this month', number_format($bedRevenueThisMonth, 3).' KWD')
                ->description('sum of bed-day charges')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Active now', $activeNow)
                ->description('currently admitted')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($activeNow > 0 ? 'danger' : 'gray'),
        ];
    }
}
