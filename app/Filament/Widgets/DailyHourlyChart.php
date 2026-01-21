<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

final class DailyHourlyChart extends ApexChartWidget
{
    /**
     * Data passed from the parent page via @livewire
     */
    public array $labels = [];

    public array $data = [];

    protected static ?string $chartId = 'dailyHourlyChart';

    protected function getOptions(): array
    {
        return [
            'chart' => [
                'type' => 'area',
                'height' => 300,
                'toolbar' => ['show' => false],
                'sparkline' => ['enabled' => false],
            ],
            'series' => [
                [
                    'name' => 'Bookings',
                    'data' => $this->data,
                ],
            ],
            'xaxis' => [
                'categories' => $this->labels,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'colors' => ['#6366f1'],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 1,
                    'opacityFrom' => 0.45,
                    'opacityTo' => 0.05,
                    'stops' => [20, 100, 100],
                ],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
        ];
    }
}
