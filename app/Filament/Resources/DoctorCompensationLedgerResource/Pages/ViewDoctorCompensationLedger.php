<?php

namespace App\Filament\Resources\DoctorCompensationLedgerResource\Pages;

use App\Filament\Resources\DoctorCompensationLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDoctorCompensationLedger extends ViewRecord
{
    protected static string $resource = DoctorCompensationLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back')
                ->url(static::$resource::getUrl('index'))
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
