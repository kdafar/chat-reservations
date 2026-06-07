<?php

namespace App\Wa\Filament\Resources\WhatsappMessageResource\Pages;

use App\Wa\Filament\Resources\WhatsappMessageResource;
use App\Wa\Filament\Widgets\MessageStatsOverview; // <-- Import the widget
use Filament\Resources\Pages\ListRecords;

class ListWhatsappMessages extends ListRecords
{
    protected static string $resource = WhatsappMessageResource::class;

    protected function getHeaderWidgets(): array // <-- ADD THIS METHOD
    {
        return [
            MessageStatsOverview::class,
        ];
    }
}
