<?php

namespace App\Filament\Resources\CuisineResource\Pages;

use App\Filament\Resources\CuisineResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditCuisine extends EditRecord
{
    use Translatable;

    protected static string $resource = CuisineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\LocaleSwitcher::make(),
        ];
    }
}
