<?php

namespace App\Filament\Resources\ClinicPaymentMethodResource\Pages;

use App\Filament\Resources\ClinicPaymentMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicPaymentMethods extends ListRecords
{
    protected static string $resource = ClinicPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
