<?php

namespace App\Filament\Resources\DoctorCompensationLedgerResource\Pages;

use App\Filament\Resources\DoctorCompensationLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDoctorCompensationLedger extends EditRecord
{
    protected static string $resource = DoctorCompensationLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
