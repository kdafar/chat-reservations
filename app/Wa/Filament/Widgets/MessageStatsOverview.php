<?php

namespace App\Wa\Filament\Widgets;

use App\Wa\Hub\Models\WhatsappMessage;
use App\Wa\Hub\Models\WhatsappSession;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MessageStatsOverview extends BasePermissionStatsOverviewWidget
{
    protected static ?string $permission = 'view_dashboard_whatsapp';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Messages', WhatsappMessage::count())
                ->description('All incoming and outgoing messages')
                ->color('primary'),
            Stat::make('Incoming Messages', WhatsappMessage::where('direction', 'incoming')->count())
                ->description('Messages received from users')
                ->color('success'),
            Stat::make('Outgoing Messages', WhatsappMessage::where('direction', 'outgoing')->count())
                ->description('Messages sent by the bot')
                ->color('info'),
            Stat::make('Active Conversations (24h)', WhatsappSession::where('last_interacted_at', '>=', now()->subDay())->count())
                ->description('Sessions with recent activity')
                ->color('warning'),
        ];
    }
}
