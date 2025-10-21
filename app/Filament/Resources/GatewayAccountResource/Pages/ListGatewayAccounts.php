<?php

namespace App\Filament\Resources\GatewayAccountResource\Pages;

use App\Filament\Resources\GatewayAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGatewayAccounts extends ListRecords
{
    protected static string $resource = GatewayAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
