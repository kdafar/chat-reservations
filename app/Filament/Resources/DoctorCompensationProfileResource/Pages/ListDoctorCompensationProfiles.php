<?php

namespace App\Filament\Resources\DoctorCompensationProfileResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\DoctorCompensationProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDoctorCompensationProfiles extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = DoctorCompensationProfileResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_doctor_compensation_profiles.what.heading'), 'body' => __('help.pages.list_doctor_compensation_profiles.what.body')],
            ['heading' => __('help.pages.list_doctor_compensation_profiles.how.heading'), 'items' => (array) trans('help.pages.list_doctor_compensation_profiles.how.items')],
            ['heading' => __('help.pages.list_doctor_compensation_profiles.faq.heading'), 'items' => (array) trans('help.pages.list_doctor_compensation_profiles.faq.items')],
        ];
    }
}
