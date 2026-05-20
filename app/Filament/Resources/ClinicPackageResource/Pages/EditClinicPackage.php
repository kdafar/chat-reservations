<?php

namespace App\Filament\Resources\ClinicPackageResource\Pages;

use App\Filament\Resources\ClinicPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClinicPackage extends EditRecord
{
    protected static string $resource = ClinicPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
