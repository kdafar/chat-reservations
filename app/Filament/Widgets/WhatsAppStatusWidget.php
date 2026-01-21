<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\WAMessageLog;
use App\Models\WhatsappSession;
use Filament\Widgets\StatsOverviewWidget as BaseWidget; // Changed from Card to Stat
use Filament\Widgets\StatsOverviewWidget\Stat;

class WhatsAppStatusWidget extends BaseWidget
{
    /**
     * The heading for the widget.
     *
     * This was changed from 'protected static' to 'protected' to match
     * the parent class (BaseWidget) and resolve the fatal error.
     */
    protected ?string $heading = 'WhatsApp Status';

    /**
     * Get the stats for the widget.
     *
     * @return array<Stat>
     */
    protected function getStats(): array // Changed from getCards() to getStats()
    {
        $today = now()->toDateString();

        $bookingsToday = Booking::whereDate('created_at', $today)->count();
        $sessions24h = WhatsappSession::where('last_interacted_at', '>=', now()->subDay())->count();
        $messagesToday = WAMessageLog::whereDate('created_at', $today)->count();

        return [
            Stat::make('Bookings Today', $bookingsToday), // Changed from Card::make to Stat::make
            Stat::make('Active Sessions (24h)', $sessions24h), // Changed from Card::make to Stat::make
            Stat::make('WA Messages Today', $messagesToday), // Changed from Card::make to Stat::make
        ];
    }
}
