<?php

namespace App\Filament\Resources\Insurance\InsuranceClaimResource\Pages;

use App\Filament\Resources\Insurance\InsuranceClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClaims extends ListRecords
{
    protected static string $resource = InsuranceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
