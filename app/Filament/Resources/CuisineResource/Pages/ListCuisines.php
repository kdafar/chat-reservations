<?php

namespace App\Filament\Resources\CuisineResource\Pages;

use App\Filament\Resources\CuisineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListCuisines extends ListRecords
{
    use Translatable;

    protected static string $resource = CuisineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\LocaleSwitcher::make(),
        ];
    }
}
