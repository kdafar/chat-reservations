<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class RevenueTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Revenue Trajectory';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full'; // Full width for impact

    protected function getData(): array
    {
        $start = $this->filters['startDate'] ?? now()->startOfMonth();
        $end = $this->filters['endDate'] ?? now()->endOfMonth();

        // Safe Trend Calculation
        $data = Trend::model(Visit::class)
            ->between(
                start: \Carbon\Carbon::parse($start),
                end: \Carbon\Carbon::parse($end)
            )
            ->perDay()
            ->sum('fees_total');

        return [
            'datasets' => [
                [
                    'label' => 'Daily Revenue (KD)',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10B981', // Emerald Green
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)', // Transparent fill
                    'fill' => 'start',
                    'tension' => 0.4, // Smooth curves
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
