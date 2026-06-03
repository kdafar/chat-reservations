<?php

namespace App\Filament\Resources\Insurance\InsurancePreauthorizationResource\Pages;

use App\Filament\Resources\Insurance\InsurancePreauthorizationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPreauthorization extends EditRecord
{
    protected static string $resource = InsurancePreauthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
