<?php

namespace App\Filament\Resources\WhatsappTriggerResource\Pages;

use App\Filament\Resources\WhatsappTriggerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreateWhatsappTrigger extends CreateRecord
{
    protected static string $resource = WhatsappTriggerResource::class;

    protected function afterSaveCommit(): void
    {
        Cache::forget('whatsapp_triggers_active');
    }
}
