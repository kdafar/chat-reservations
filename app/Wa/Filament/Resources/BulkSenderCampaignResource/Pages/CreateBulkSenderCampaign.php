<?php

namespace App\Wa\Filament\Resources\BulkSenderCampaignResource\Pages;

use App\Wa\Filament\Resources\BulkSenderCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBulkSenderCampaign extends CreateRecord
{
    protected static string $resource = BulkSenderCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure DB always stores int, while UI stays array.
        if (array_key_exists('header_media_id', $data)) {
            $data['header_media_id'] = BulkSenderCampaignResource::extractCuratorMediaId($data['header_media_id']);
        }

        return $data;
    }
}
