<?php

namespace App\Filament\Resources\ClinicStockMovementResource\Pages;

use App\Filament\Resources\ClinicStockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicStockMovements extends ListRecords
{
    protected static string $resource = ClinicStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
