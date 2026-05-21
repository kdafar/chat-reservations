<?php

namespace App\Filament\Resources\Accounting\JournalEntryResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Accounting\JournalEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_journal_entries.what.heading'), 'body' => __('help.pages.list_journal_entries.what.body')],
            ['heading' => __('help.pages.list_journal_entries.how.heading'), 'items' => (array) trans('help.pages.list_journal_entries.how.items')],
            ['heading' => __('help.pages.list_journal_entries.faq.heading'), 'items' => (array) trans('help.pages.list_journal_entries.faq.items')],
        ];
    }
}
