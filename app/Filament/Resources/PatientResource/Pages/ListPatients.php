<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPatients extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_patients.what.heading'), 'body' => __('help.pages.list_patients.what.body')],
            ['heading' => __('help.pages.list_patients.how.heading'), 'items' => (array) trans('help.pages.list_patients.how.items')],
            ['heading' => __('help.pages.list_patients.faq.heading'), 'items' => (array) trans('help.pages.list_patients.faq.items')],
        ];
    }
}
