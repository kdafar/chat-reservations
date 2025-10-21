<?php

namespace App\Filament\Resources\BranchIntegrationResource\Pages;

use App\Filament\Resources\BranchIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranchIntegration extends EditRecord
{
    protected static string $resource = BranchIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
