<?php

namespace App\Filament\Resources\ClinicPaymentMethodResource\Pages;

use App\Filament\Resources\ClinicPaymentMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClinicPaymentMethod extends EditRecord
{
    protected static string $resource = ClinicPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
