<?php

namespace App\Filament\Resources\MessageTextResource\Pages;

use App\Filament\Resources\MessageTextResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMessageTexts extends ListRecords
{
    protected static string $resource = MessageTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
