<?php

namespace App\Wa\Filament\Widgets;

use App\Wa\Hub\Models\WhatsappSession;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserReportStatsWidget extends BasePermissionTableWidget
{
    public ?array $filters = []; // This will receive the filter data from the page

    protected static ?string $permission = 'view_dashboard_reports';

    public static function canView(): bool
    {
        return false;
    }

    protected function getStats(): array
    {
        // Start with a base query
        $query = WhatsappSession::query()->whereNotNull('last_interacted_at');

        // Apply filters if they exist
        if ($days = $this->filters['inactivity_period'] ?? null) {
            $query->where('last_interacted_at', '<=', now()->subDays((int) $days));
        }
        if ($restaurantId = $this->filters['restaurant_id'] ?? null) {
            $query->where('selected_vendor_id', $restaurantId);
        }

        // Clone the query to calculate different stats without re-applying filters
        $totalUsersQuery = clone $query;
        $inactive7DaysQuery = clone $query;
        $inactive30DaysQuery = clone $query;

        // Calculate the stats based on the (potentially filtered) query
        $totalUsers = $totalUsersQuery->count();
        $inactive7Days = $inactive7DaysQuery->where('last_interacted_at', '<=', now()->subDays(7))->count();
        $inactive30Days = $inactive30DaysQuery->where('last_interacted_at', '<=', now()->subDays(30))->count();

        // Return the stats as cards
        return [
            Stat::make('Matching Users', $totalUsers)
                ->description('Users matching the current filters')
                ->color('success'),
            Stat::make('Inactive (7+ days)', $inactive7Days)
                ->description('Matching users not seen in 7+ days')
                ->color('warning'),
            Stat::make('Inactive (30+ days)', $inactive30Days)
                ->description('Matching users not seen in 30+ days')
                ->color('danger'),
        ];
    }
}
