<?php

namespace App\Filament\Resources\ClinicPackageResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\ClinicPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicPackages extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = ClinicPackageResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_clinic_packages.what.heading'), 'body' => __('help.pages.list_clinic_packages.what.body')],
            ['heading' => __('help.pages.list_clinic_packages.how.heading'), 'items' => (array) trans('help.pages.list_clinic_packages.how.items')],
            ['heading' => __('help.pages.list_clinic_packages.faq.heading'), 'items' => (array) trans('help.pages.list_clinic_packages.faq.items')],
        ];
    }
}
