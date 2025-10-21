<?php

namespace App\Filament\Resources\CuisineResource\Pages;

use App\Filament\Resources\CuisineResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateCuisine extends CreateRecord
{
    use Translatable;

    protected static string $resource = CuisineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
