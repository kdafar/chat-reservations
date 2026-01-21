<?php

namespace App\Filament\Resources\BranchAvailabilityRuleResource\Pages;

use App\Filament\Resources\BranchAvailabilityRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBranchAvailabilityRules extends ListRecords
{
    protected static string $resource = BranchAvailabilityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
