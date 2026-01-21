<?php

namespace App\Filament\Resources\ClinicStockMovementResource\Pages;

use App\Filament\Resources\ClinicStockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClinicStockMovement extends EditRecord
{
    protected static string $resource = ClinicStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
