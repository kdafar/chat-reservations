<?php

namespace App\Filament\Resources\Insurance\InsurancePlanResource\Pages;

use App\Filament\Resources\Insurance\InsurancePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlans extends ListRecords
{
    protected static string $resource = InsurancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
