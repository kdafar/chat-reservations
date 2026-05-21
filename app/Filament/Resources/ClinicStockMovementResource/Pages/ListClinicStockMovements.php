<?php

namespace App\Filament\Resources\ClinicStockMovementResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\ClinicStockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinicStockMovements extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = ClinicStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_clinic_stock_movements.what.heading'), 'body' => __('help.pages.list_clinic_stock_movements.what.body')],
            ['heading' => __('help.pages.list_clinic_stock_movements.how.heading'), 'items' => (array) trans('help.pages.list_clinic_stock_movements.how.items')],
            ['heading' => __('help.pages.list_clinic_stock_movements.faq.heading'), 'items' => (array) trans('help.pages.list_clinic_stock_movements.faq.items')],
        ];
    }
}
