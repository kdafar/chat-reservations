<?php

namespace App\Filament\Resources\DoctorCompensationLedgerResource\Pages;

use App\Filament\Resources\DoctorCompensationLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDoctorCompensationLedgers extends ListRecords
{
    protected static string $resource = DoctorCompensationLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => null),
        ];
    }
}
