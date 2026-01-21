<?php

namespace App\Filament\Resources\ReservationTermResource\Pages;

use App\Filament\Resources\ReservationTermResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReservationTerm extends EditRecord
{
    protected static string $resource = ReservationTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
