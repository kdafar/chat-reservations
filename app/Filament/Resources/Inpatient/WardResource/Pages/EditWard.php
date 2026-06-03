<?php

namespace App\Filament\Resources\Inpatient\WardResource\Pages;

use App\Filament\Resources\Inpatient\WardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWard extends EditRecord
{
    protected static string $resource = WardResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
