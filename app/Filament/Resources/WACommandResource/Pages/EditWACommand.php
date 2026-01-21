<?php

namespace App\Filament\Resources\WACommandResource\Pages;

use App\Filament\Resources\WACommandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWACommand extends EditRecord
{
    protected static string $resource = WACommandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
