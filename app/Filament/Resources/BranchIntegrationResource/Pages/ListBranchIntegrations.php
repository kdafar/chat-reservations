<?php

namespace App\Filament\Resources\BranchIntegrationResource\Pages;

use App\Filament\Resources\BranchIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBranchIntegrations extends ListRecords
{
    protected static string $resource = BranchIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
