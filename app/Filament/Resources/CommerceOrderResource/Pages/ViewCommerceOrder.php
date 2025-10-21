<?php

namespace App\Filament\Resources\CommerceOrderResource\Pages;

use App\Filament\Resources\CommerceOrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommerceOrder extends ViewRecord
{
    protected static string $resource = CommerceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return []; // you can add a 'Print' or 'Mark Delivered' action later
    }
}
