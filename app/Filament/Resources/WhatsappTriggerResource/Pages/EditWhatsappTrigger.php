<?php

namespace App\Filament\Resources\WhatsappTriggerResource\Pages;

use App\Filament\Resources\WhatsappTriggerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditWhatsappTrigger extends EditRecord
{
    protected static string $resource = WhatsappTriggerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSaveCommit(): void
    {
        Cache::forget('whatsapp_triggers_active');
    }
}
