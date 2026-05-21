<?php

namespace App\Filament\Resources\Accounting\JournalEntryResource\Pages;

use App\Filament\Resources\Accounting\JournalEntryResource;
use App\Models\Accounting\JournalEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Status starts as draft; admin then clicks "Post Draft" from the list.
        $data['status'] = JournalEntry::STATUS_DRAFT;
        $data['currency'] = $data['currency'] ?? 'KWD';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Journal entry saved as draft')
            ->body('Click "Post Draft" from the list to validate balance and post.')
            ->success()
            ->send();
    }
}
