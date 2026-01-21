<?php

namespace App\Filament\Resources\MessageTextResource\Pages;

use App\Filament\Resources\MessageTextResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMessageText extends EditRecord
{
    protected static string $resource = MessageTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
