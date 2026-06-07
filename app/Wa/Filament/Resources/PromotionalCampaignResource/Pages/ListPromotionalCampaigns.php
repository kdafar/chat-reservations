<?php

namespace App\Wa\Filament\Resources\PromotionalCampaignResource\Pages;

use App\Wa\Filament\Resources\PromotionalCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotionalCampaigns extends ListRecords
{
    protected static string $resource = PromotionalCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
