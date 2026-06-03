<?php

namespace App\Filament\Resources\Insurance\InsuranceClaimResource\Pages;

use App\Filament\Resources\Insurance\InsuranceClaimResource;
use App\Services\Insurance\InsuranceService;
use Filament\Resources\Pages\CreateRecord;

class CreateClaim extends CreateRecord
{
    protected static string $resource = InsuranceClaimResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['claim_number'])) {
            $data['claim_number'] = app(InsuranceService::class)->generateClaimNumber();
        }

        if (empty($data['status'])) {
            $data['status'] = \App\Models\Insurance\InsuranceClaim::STATUS_DRAFT;
        }

        return $data;
    }
}
