<?php

namespace App\Filament\Resources\VisitStockRequestResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\VisitStockRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListVisitStockRequests extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = VisitStockRequestResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_visit_stock_requests.what.heading'), 'body' => __('help.pages.list_visit_stock_requests.what.body')],
            ['heading' => __('help.pages.list_visit_stock_requests.how.heading'), 'items' => (array) trans('help.pages.list_visit_stock_requests.how.items')],
            ['heading' => __('help.pages.list_visit_stock_requests.faq.heading'), 'items' => (array) trans('help.pages.list_visit_stock_requests.faq.items')],
        ];
    }
}
