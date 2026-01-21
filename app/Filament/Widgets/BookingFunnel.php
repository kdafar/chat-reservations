<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingFunnel extends BaseWidget
{
    // must be NON-static to match the parent
    protected ?string $heading = 'Booking Funnel (Last 7 days)';

    // Optional: make it span full width
    protected int|string|array $columnSpan = 'full';

    // Optional: auto-refresh every minute (Filament v3 supports this)
    // protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $from = now()->subDays(7)->startOfDay();

        $all = Booking::where('created_at', '>=', $from)->count();
        $confirmed = Booking::where('created_at', '>=', $from)->where('status', 'confirmed')->count();
        $seated = Booking::where('created_at', '>=', $from)->where('status', 'seated')->count();
        $completed = Booking::where('created_at', '>=', $from)->where('status', 'completed')->count();

        $pct = fn (int $num, int $den) => $den > 0 ? round(($num / $den) * 100) : 0;

        return [
            Stat::make('Created', (string) $all)
                ->description('Bookings created'),

            Stat::make('Confirmed', (string) $confirmed)
                ->description($pct($confirmed, $all).'% of created'),

            Stat::make('Seated', (string) $seated)
                ->description($pct($seated, $confirmed).'% of confirmed'),

            Stat::make('Completed', (string) $completed)
                ->description($pct($completed, $seated).'% of seated'),
        ];
    }

    // Alternatively, you could do this instead of the property above:
    // protected function getHeading(): string
    // {
    //     return 'Booking Funnel (Last 7 days)';
    // }
}
