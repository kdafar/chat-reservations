<?php

namespace App\Filament\Resources\ClinicItemStockResource\Pages;

use App\Filament\Resources\ClinicItemStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicItemStocks extends ListRecords
{
    protected static string $resource = ClinicItemStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
