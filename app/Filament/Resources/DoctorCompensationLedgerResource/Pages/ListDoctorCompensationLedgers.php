<?php

namespace App\Filament\Resources\DoctorCompensationLedgerResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\DoctorCompensationLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDoctorCompensationLedgers extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = DoctorCompensationLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => null),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_doctor_compensation_ledgers.what.heading'), 'body' => __('help.pages.list_doctor_compensation_ledgers.what.body')],
            ['heading' => __('help.pages.list_doctor_compensation_ledgers.how.heading'), 'items' => (array) trans('help.pages.list_doctor_compensation_ledgers.how.items')],
            ['heading' => __('help.pages.list_doctor_compensation_ledgers.faq.heading'), 'items' => (array) trans('help.pages.list_doctor_compensation_ledgers.faq.items')],
        ];
    }
}
