<?php

namespace App\Filament\Resources\WhatsappTriggerResource\Pages;

use App\Filament\Resources\WhatsappTriggerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappTriggers extends ListRecords
{
    protected static string $resource = WhatsappTriggerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
