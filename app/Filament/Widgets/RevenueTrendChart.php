<?php

namespace App\Filament\Widgets;

use App\Models\Visit;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Revenue Trajectory';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $start = Carbon::parse($this->filters['startDate'] ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($this->filters['endDate'] ?? now()->endOfMonth())->endOfDay();

        // Revenue per day = fees + packages + items_price − discount.
        // (Audit follow-up review: fees_total alone undercounts packages + items.)
        $rows = Visit::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('SUM(COALESCE(fees_total,0) + COALESCE(packages_price_total,0) + COALESCE(items_price_total,0) - COALESCE(discount_total,0)) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        // Fill in zeros for days without activity so the chart line is continuous.
        $labels = [];
        $data = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $key;
            $data[] = (float) ($rows[$key]->revenue ?? 0);
            $cursor->addDay();
        }

        return [
            'datasets' => [[
                'label' => 'Daily Revenue (KWD)',
                'data' => $data,
                'borderColor' => '#10B981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => 'start',
                'tension' => 0.4,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
