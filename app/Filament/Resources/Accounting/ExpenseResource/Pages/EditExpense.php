<?php

namespace App\Filament\Resources\Accounting\ExpenseResource\Pages;

use App\Filament\Resources\Accounting\ExpenseResource;
use App\Models\Accounting\Expense;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->getRecord()?->status === Expense::STATUS_DRAFT),
        ];
    }
}
