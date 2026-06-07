<?php

namespace App\Wa\Filament\Resources\BulkSenderCampaignResource\Pages;

use App\Wa\Filament\Resources\BulkSenderCampaignResource;
use App\Wa\Filament\Resources\BulkSenderCampaignResource\Widgets\BulkSenderStatsOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBulkSenderCampaigns extends ListRecords
{
    protected static string $resource = BulkSenderCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BulkSenderStatsOverview::class,
        ];
    }
}
