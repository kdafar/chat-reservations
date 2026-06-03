<?php

namespace App\Filament\Widgets\Inpatient;

use App\Models\Inpatient\AdmissionBedStay;
use App\Models\Inpatient\Bed;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

/**
 * Daily occupancy percentage over the last 30 days. A bed counts as
 * occupied for a given day if there was an open bed_stay overlapping
 * that day's midnight.
 */
class BedOccupancyTrend extends ApexChartWidget
{
    protected static ?string $chartId = 'inpatientBedOccupancyTrend';

    protected static ?string $heading = 'Bed occupancy — last 30 days';

    protected static ?int $contentHeight = 280;

    protected static ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return \App\Models\Inpatient\Ward::query()->exists();
    }

    protected function getOptions(): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $totalBeds = (int) Bed::query()->where('is_active', true)->count();
        $days = 30;
        $start = now($tz)->subDays($days - 1)->startOfDay();

        $labels = [];
        $occupiedSeries = [];
        $occupancyPctSeries = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $endOfDay = $day->copy()->endOfDay();

            $occupied = AdmissionBedStay::query()
                ->where('assigned_at', '<=', $endOfDay)
                ->where(function ($q) use ($endOfDay) {
                    $q->whereNull('released_at')->orWhere('released_at', '>', $endOfDay);
                })
                ->distinct('bed_id')
                ->count('bed_id');

            $labels[] = $day->format('M j');
            $occupiedSeries[] = $occupied;
            $occupancyPctSeries[] = $totalBeds > 0 ? round(($occupied / $totalBeds) * 100, 1) : 0;
        }

        return [
            'chart' => [
                'type' => 'area',
                'height' => 280,
                'toolbar' => ['show' => false],
                'zoom' => ['enabled' => false],
            ],
            'stroke' => ['curve' => 'smooth', 'width' => 2],
            'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.4, 'opacityTo' => 0.05]],
            'dataLabels' => ['enabled' => false],
            'series' => [
                ['name' => 'Occupancy %', 'data' => $occupancyPctSeries],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels' => ['style' => ['fontFamily' => 'inherit']],
            ],
            'yaxis' => [
                'max' => 100,
                'labels' => [
                    'style' => ['fontFamily' => 'inherit'],
                    'formatter' => 'function (val) { return val + "%"; }',
                ],
            ],
            'colors' => ['#0d9488'],
            'tooltip' => [
                'y' => [
                    'formatter' => 'function (val) { return val + "%"; }',
                ],
            ],
        ];
    }
}
