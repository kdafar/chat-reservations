<?php

namespace App\Wa\Filament\WhatsApp\Resources\WaTemplateResource\Pages;

use App\Wa\Filament\WhatsApp\Resources\WaTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaTemplates extends ListRecords
{
    protected static string $resource = WaTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
