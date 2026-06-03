<?php

namespace App\Filament\Resources\Lab\LabTestResource\Pages;

use App\Filament\Resources\Lab\LabTestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLabTests extends ListRecords
{
    protected static string $resource = LabTestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
