<?php

namespace App\Filament\Resources\Insurance\PatientInsurancePolicyResource\Pages;

use App\Filament\Resources\Insurance\PatientInsurancePolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPolicies extends ListRecords
{
    protected static string $resource = PatientInsurancePolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
