<?php

namespace App\Filament\Resources\Insurance\InsurancePlanResource\Pages;

use App\Filament\Resources\Insurance\InsurancePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = InsurancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
