<?php

namespace App\Filament\Resources\Insurance\InsurerResource\Pages;

use App\Filament\Resources\Insurance\InsurerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInsurer extends EditRecord
{
    protected static string $resource = InsurerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
