<?php

namespace App\Filament\Resources\VisitStockRequestResource\Pages;

use App\Filament\Resources\VisitStockRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVisitStockRequest extends EditRecord
{
    protected static string $resource = VisitStockRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
