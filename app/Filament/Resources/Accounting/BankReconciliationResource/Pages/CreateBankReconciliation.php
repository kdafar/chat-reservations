<?php

namespace App\Filament\Resources\Accounting\BankReconciliationResource\Pages;

use App\Filament\Resources\Accounting\BankReconciliationResource;
use App\Models\Accounting\BankReconciliation;
use Filament\Resources\Pages\CreateRecord;

class CreateBankReconciliation extends CreateRecord
{
    protected static string $resource = BankReconciliationResource::class;

    /**
     * Once the row is in the DB, populate the book-side balances by reading
     * the GL. The accountant lands on the edit page with both sides filled.
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if ($record instanceof BankReconciliation) {
            $record->recomputeBookBalances();
            $record->save();
        }
    }
}
