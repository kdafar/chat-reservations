<?php

namespace App\Wa\Filament\Resources\RatingResource\Widgets;

use App\Wa\Filament\Resources\RatingResource\Pages\ListRatings;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class RatingStatsNoTrait extends BaseWidget
{
    // Optional: auto-refresh so numbers follow filter changes without manual reloads
    protected static ?string $pollingInterval = '6s';

    protected function getStats(): array
    {
        $page = Filament::getCurrentPage();

        // Only render on the Ratings list page
        if (! $page instanceof ListRatings) {
            return [];
        }

        /** @var Builder $query */
        $query = $page->getFilteredTableQuery(); //  same query the table uses (with filters/search/sorts)

        $avg = (float) ((clone $query)->avg('rating') ?? 0);
        $count = (clone $query)->count();
        $week = (clone $query)->where('created_at', '>=', now()->subWeek())->count();

        // (Optional) quick sanity log
        // \Log::info('RatingStatsNoTrait', compact('count', 'avg', 'week'));

        return [
            Stat::make('Total Ratings', $count)
                ->description('Based on current filters')
                ->descriptionIcon('heroicon-m-star')
                ->color('primary'),

            Stat::make('Average Rating', number_format($avg, 2).' / 5')
                ->description('Average for filtered ratings')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($avg >= 4 ? 'success' : ($avg >= 2.5 ? 'warning' : 'danger')),

            Stat::make('Ratings This Week', $week)
                ->description('Within the filtered results')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
