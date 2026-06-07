<?php

namespace App\Wa\Filament\WhatsApp\Resources\WaTemplateResource\Pages;

use App\Wa\Filament\WhatsApp\Resources\WaTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaTemplate extends EditRecord
{
    protected static string $resource = WaTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
