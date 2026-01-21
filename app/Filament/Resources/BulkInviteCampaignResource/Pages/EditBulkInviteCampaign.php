<?php

namespace App\Filament\Resources\BulkInviteCampaignResource\Pages;

use App\Filament\Resources\BulkInviteCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBulkInviteCampaign extends EditRecord
{
    protected static string $resource = BulkInviteCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Campaign saved';
    }
}
