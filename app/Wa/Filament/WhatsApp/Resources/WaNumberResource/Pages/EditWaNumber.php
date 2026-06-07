<?php

namespace App\Wa\Filament\WhatsApp\Resources\WaNumberResource\Pages;

use App\Wa\Filament\WhatsApp\Resources\WaNumberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaNumber extends EditRecord
{
    protected static string $resource = WaNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
