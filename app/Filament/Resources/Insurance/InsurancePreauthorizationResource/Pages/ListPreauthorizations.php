<?php

namespace App\Filament\Resources\Insurance\InsurancePreauthorizationResource\Pages;

use App\Filament\Resources\Insurance\InsurancePreauthorizationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPreauthorizations extends ListRecords
{
    protected static string $resource = InsurancePreauthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Pre-auth'),
        ];
    }
}
