<?php

namespace App\Filament\Resources\Insurance\InsurancePreauthorizationResource\Pages;

use App\Filament\Resources\Insurance\InsurancePreauthorizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePreauthorization extends CreateRecord
{
    protected static string $resource = InsurancePreauthorizationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['requested_by_user_id'])) {
            $data['requested_by_user_id'] = auth()->id();
        }

        return $data;
    }
}
