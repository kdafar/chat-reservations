<?php

namespace App\Filament\Resources\ClinicalPhraseResource\Pages;

use App\Filament\Resources\ClinicalPhraseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicalPhrases extends ListRecords
{
    protected static string $resource = ClinicalPhraseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
