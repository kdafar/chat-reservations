<?php

namespace App\Filament\Resources\DoctorCompensationProfileResource\Pages;

use App\Filament\Resources\DoctorCompensationProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDoctorCompensationProfile extends EditRecord
{
    protected static string $resource = DoctorCompensationProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
