<?php

namespace App\Filament\Resources\BranchBlackoutResource\Pages;

use App\Filament\Resources\BranchBlackoutResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranchBlackout extends EditRecord
{
    protected static string $resource = BranchBlackoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
