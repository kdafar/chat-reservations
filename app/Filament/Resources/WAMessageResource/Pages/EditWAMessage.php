<?php

namespace App\Filament\Resources\WAMessageResource\Pages;

use App\Filament\Resources\WAMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWAMessage extends EditRecord
{
    protected static string $resource = WAMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
