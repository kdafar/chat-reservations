<?php

namespace App\Filament\Resources\CommercePaymentPolicyResource\Pages;

use App\Filament\Resources\CommercePaymentPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommercePaymentPolicies extends ListRecords
{
    protected static string $resource = CommercePaymentPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
