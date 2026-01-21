<?php

namespace App\Filament\Resources\ReservationTermResource\Pages;

use App\Filament\Resources\ReservationTermResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageReservationTerms extends ManageRecords
{
    protected static string $resource = ReservationTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
