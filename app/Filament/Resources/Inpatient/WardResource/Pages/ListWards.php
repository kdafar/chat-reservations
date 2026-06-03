<?php

namespace App\Filament\Resources\Inpatient\WardResource\Pages;

use App\Filament\Resources\Inpatient\WardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWards extends ListRecords
{
    protected static string $resource = WardResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
