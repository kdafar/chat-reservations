<?php

namespace App\Filament\Resources\Accounting\JournalEntryResource\Pages;

use App\Filament\Resources\Accounting\JournalEntryResource;
use App\Models\Accounting\JournalEntry;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJournalEntry extends EditRecord
{
    protected static string $resource = JournalEntryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Hard guard: only drafts are editable. Posted/reversed entries view-only.
        if ($this->getRecord()->status !== JournalEntry::STATUS_DRAFT) {
            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)
                    && $this->getRecord()->status === JournalEntry::STATUS_DRAFT),
        ];
    }
}
