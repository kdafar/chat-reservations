<?php

namespace App\Filament\Resources\DoctorResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\DoctorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDoctors extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = DoctorResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_doctors.what.heading'), 'body' => __('help.pages.list_doctors.what.body')],
            ['heading' => __('help.pages.list_doctors.how.heading'), 'items' => (array) trans('help.pages.list_doctors.how.items')],
            ['heading' => __('help.pages.list_doctors.faq.heading'), 'items' => (array) trans('help.pages.list_doctors.faq.items')],
        ];
    }
}
