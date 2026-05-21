<?php

namespace App\Filament\Resources\Accounting\ChartOfAccountResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Accounting\ChartOfAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChartOfAccounts extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = ChartOfAccountResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([Actions\CreateAction::make()]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_chart_of_accounts.what.heading'), 'body' => __('help.pages.list_chart_of_accounts.what.body')],
            ['heading' => __('help.pages.list_chart_of_accounts.numbering.heading'), 'items' => (array) trans('help.pages.list_chart_of_accounts.numbering.items')],
            ['heading' => __('help.pages.list_chart_of_accounts.how.heading'), 'items' => (array) trans('help.pages.list_chart_of_accounts.how.items')],
            ['heading' => __('help.pages.list_chart_of_accounts.faq.heading'), 'items' => (array) trans('help.pages.list_chart_of_accounts.faq.items')],
        ];
    }
}
