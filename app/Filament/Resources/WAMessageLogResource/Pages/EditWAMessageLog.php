<?php

namespace App\Filament\Resources\WAMessageLogResource\Pages;

use App\Filament\Resources\WAMessageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWAMessageLog extends EditRecord
{
    protected static string $resource = WAMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
