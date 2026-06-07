<?php

namespace App\Wa\Filament\Resources\WhatsappSessionResource\Pages;

use App\Wa\Filament\Resources\WhatsappSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappSessions extends ListRecords
{
    protected static string $resource = WhatsappSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
