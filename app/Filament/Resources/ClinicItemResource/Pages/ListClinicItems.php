<?php

namespace App\Filament\Resources\ClinicItemResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\ClinicItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicItems extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = ClinicItemResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_clinic_items.what.heading'), 'body' => __('help.pages.list_clinic_items.what.body')],
            ['heading' => __('help.pages.list_clinic_items.how.heading'), 'items' => (array) trans('help.pages.list_clinic_items.how.items')],
            ['heading' => __('help.pages.list_clinic_items.faq.heading'), 'items' => (array) trans('help.pages.list_clinic_items.faq.items')],
        ];
    }
}
