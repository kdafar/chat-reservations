<?php

namespace App\Filament\Resources\Inpatient\BedResource\Pages;

use App\Filament\Resources\Inpatient\BedResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBed extends EditRecord
{
    protected static string $resource = BedResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
