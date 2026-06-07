<?php

namespace App\Wa\Filament\WhatsApp\Resources\WaAccountResource\Pages;

use App\Wa\Filament\WhatsApp\Resources\WaAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaAccounts extends ListRecords
{
    protected static string $resource = WaAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
