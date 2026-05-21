<?php

namespace App\Filament\Resources\Accounting\VendorResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Accounting\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([Actions\CreateAction::make()]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_vendors.what.heading'), 'body' => __('help.pages.list_vendors.what.body')],
            ['heading' => __('help.pages.list_vendors.how.heading'), 'items' => (array) trans('help.pages.list_vendors.how.items')],
            ['heading' => __('help.pages.list_vendors.faq.heading'), 'items' => (array) trans('help.pages.list_vendors.faq.items')],
        ];
    }
}
