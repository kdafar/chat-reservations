<?php

namespace App\Filament\Resources\Insurance\PatientInsurancePolicyResource\Pages;

use App\Filament\Resources\Insurance\PatientInsurancePolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPolicy extends EditRecord
{
    protected static string $resource = PatientInsurancePolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
