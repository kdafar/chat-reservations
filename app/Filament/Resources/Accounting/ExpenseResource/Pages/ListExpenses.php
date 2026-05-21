<?php

namespace App\Filament\Resources\Accounting\ExpenseResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Accounting\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([Actions\CreateAction::make()]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_expenses.what.heading'), 'body' => __('help.pages.list_expenses.what.body')],
            ['heading' => __('help.pages.list_expenses.how.heading'), 'items' => (array) trans('help.pages.list_expenses.how.items')],
            ['heading' => __('help.pages.list_expenses.faq.heading'), 'items' => (array) trans('help.pages.list_expenses.faq.items')],
        ];
    }
}
