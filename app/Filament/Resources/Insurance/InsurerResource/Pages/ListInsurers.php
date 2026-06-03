<?php

namespace App\Filament\Resources\Insurance\InsurerResource\Pages;

use App\Filament\Resources\Insurance\InsurerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInsurers extends ListRecords
{
    protected static string $resource = InsurerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
