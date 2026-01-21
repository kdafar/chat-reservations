<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

final class RevenueCompositionChart extends ApexChartWidget
{
    /**
     * Data passed from the parent page via @livewire
     */
    public array $labels = [];

    public array $series = [];

    protected static ?string $chartId = 'revenueCompositionChart';

    protected function getOptions(): array
    {
        return [
            'chart' => [
                'type' => 'donut',
                'height' => 300,
            ],
            'series' => array_map('floatval', $this->series),
            'labels' => $this->labels,
            'legend' => [
                'position' => 'bottom',
                'fontFamily' => 'inherit',
            ],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '70%',
                    ],
                ],
            ],
            'colors' => ['#10b981', '#f59e0b', '#ef4444'],
            'dataLabels' => [
                'enabled' => false,
            ],
        ];
    }
}
