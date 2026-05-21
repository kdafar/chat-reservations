<?php

namespace App\Filament\Resources\ClinicItemStockResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\ClinicItemStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicItemStocks extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = ClinicItemStockResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_clinic_item_stocks.what.heading'), 'body' => __('help.pages.list_clinic_item_stocks.what.body')],
            ['heading' => __('help.pages.list_clinic_item_stocks.how.heading'), 'items' => (array) trans('help.pages.list_clinic_item_stocks.how.items')],
            ['heading' => __('help.pages.list_clinic_item_stocks.faq.heading'), 'items' => (array) trans('help.pages.list_clinic_item_stocks.faq.items')],
        ];
    }
}
