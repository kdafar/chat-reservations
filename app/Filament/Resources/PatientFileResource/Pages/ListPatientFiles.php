<?php

namespace App\Filament\Resources\PatientFileResource\Pages;

use App\Filament\Resources\PatientFileResource;
use Filament\Resources\Pages\ListRecords;

class ListPatientFiles extends ListRecords
{
    protected static string $resource = PatientFileResource::class;

    protected function getHeaderActions(): array
    {
        // No Create action — files can only be uploaded via the patient's relation manager.
        return [];
    }
}
