<?php

namespace App\Filament\Resources\Inpatient\AdmissionResource\Pages;

use App\Filament\Resources\Inpatient\AdmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdmissions extends ListRecords
{
    protected static string $resource = AdmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Admit patient')];
    }
}
