<?php

namespace App\Filament\Resources\Accounting\BankReconciliationResource\Pages;

use App\Filament\Resources\Accounting\BankReconciliationResource;
use App\Models\Accounting\BankReconciliation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBankReconciliation extends EditRecord
{
    protected static string $resource = BankReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(function () {
                    $record = $this->getRecord();

                    return $record instanceof BankReconciliation
                        && $record->status === BankReconciliation::STATUS_IN_PROGRESS;
                }),
        ];
    }
}
