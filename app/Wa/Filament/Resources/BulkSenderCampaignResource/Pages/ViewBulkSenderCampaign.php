<?php

namespace App\Wa\Filament\Resources\BulkSenderCampaignResource\Pages; // <--- FIXED NAMESPACE

use App\Wa\Filament\Resources\BulkSenderCampaignResource; // <--- FIXED IMPORT
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBulkSenderCampaign extends ViewRecord
{
    // FIXED RESOURCE CLASS
    protected static string $resource = BulkSenderCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
