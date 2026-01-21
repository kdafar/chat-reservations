<?php

namespace App\Filament\Resources\ClinicItemResource\Pages;

use App\Filament\Resources\ClinicItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicItems extends ListRecords
{
    protected static string $resource = ClinicItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
