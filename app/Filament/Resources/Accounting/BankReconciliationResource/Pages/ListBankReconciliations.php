<?php

namespace App\Filament\Resources\Accounting\BankReconciliationResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Accounting\BankReconciliationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankReconciliations extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = BankReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([Actions\CreateAction::make()]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_bank_reconciliations.what.heading'), 'body' => __('help.pages.list_bank_reconciliations.what.body')],
            ['heading' => __('help.pages.list_bank_reconciliations.how.heading'), 'items' => (array) trans('help.pages.list_bank_reconciliations.how.items')],
            ['heading' => __('help.pages.list_bank_reconciliations.faq.heading'), 'items' => (array) trans('help.pages.list_bank_reconciliations.faq.items')],
        ];
    }
}
