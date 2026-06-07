<?php

namespace App\Wa\Filament\WhatsApp\Resources\WaAccountResource\Pages;

use App\Wa\Filament\WhatsApp\Resources\WaAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaAccount extends EditRecord
{
    protected static string $resource = WaAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
