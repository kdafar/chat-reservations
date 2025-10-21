<?php

namespace App\Filament\Resources\CommerceOrderResource\Pages;

use App\Filament\Resources\CommerceOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListCommerceOrders extends ListRecords
{
    protected static string $resource = CommerceOrderResource::class;

    // Auto-refresh every 10s so new orders pop in
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getPollingInterval(): ?string
    {
        return '10s';
    }
}
