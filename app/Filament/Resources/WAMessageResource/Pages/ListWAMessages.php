<?php

namespace App\Filament\Resources\WAMessageResource\Pages;

use App\Filament\Resources\WAMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWAMessages extends ListRecords
{
    protected static string $resource = WAMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
