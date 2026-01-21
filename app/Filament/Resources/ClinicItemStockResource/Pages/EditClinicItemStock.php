<?php

namespace App\Filament\Resources\ClinicItemStockResource\Pages;

use App\Filament\Resources\ClinicItemStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClinicItemStock extends EditRecord
{
    protected static string $resource = ClinicItemStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
