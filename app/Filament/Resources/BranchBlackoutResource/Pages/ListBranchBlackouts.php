<?php

namespace App\Filament\Resources\BranchBlackoutResource\Pages;

use App\Filament\Resources\BranchBlackoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBranchBlackouts extends ListRecords
{
    protected static string $resource = BranchBlackoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
