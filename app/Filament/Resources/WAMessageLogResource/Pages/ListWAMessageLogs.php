<?php

namespace App\Filament\Resources\WAMessageLogResource\Pages;

use App\Filament\Resources\WAMessageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWAMessageLogs extends ListRecords
{
    protected static string $resource = WAMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
