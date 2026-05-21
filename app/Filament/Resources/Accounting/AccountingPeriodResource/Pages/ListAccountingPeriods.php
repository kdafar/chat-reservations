<?php

namespace App\Filament\Resources\Accounting\AccountingPeriodResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Accounting\AccountingPeriodResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountingPeriods extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = AccountingPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_accounting_periods.what.heading'), 'body' => __('help.pages.list_accounting_periods.what.body')],
            ['heading' => __('help.pages.list_accounting_periods.how.heading'), 'items' => (array) trans('help.pages.list_accounting_periods.how.items')],
            ['heading' => __('help.pages.list_accounting_periods.faq.heading'), 'items' => (array) trans('help.pages.list_accounting_periods.faq.items')],
        ];
    }
}
