<?php

namespace App\Filament\Resources\BulkInviteCampaignResource\Pages;

use App\Filament\Resources\BulkInviteCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBulkInviteCampaign extends ViewRecord
{
    protected static string $resource = BulkInviteCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
