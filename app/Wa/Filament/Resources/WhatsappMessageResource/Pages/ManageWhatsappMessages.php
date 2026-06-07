<?php

namespace App\Wa\Filament\Resources\WhatsappMessageResource\Pages;

use App\Wa\Filament\Resources\WhatsappMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageWhatsappMessages extends ManageRecords
{
    protected static string $resource = WhatsappMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
