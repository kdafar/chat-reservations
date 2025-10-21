<?php

namespace App\Filament\Resources\CommerceOrderResource\Pages;

use App\Filament\Resources\CommerceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommerceOrder extends EditRecord
{
    protected static string $resource = CommerceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
