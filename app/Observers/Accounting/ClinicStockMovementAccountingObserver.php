<?php

namespace App\Observers\Accounting;

use App\Models\ClinicStockMovement;
use App\Services\Accounting\AccountingService;

class ClinicStockMovementAccountingObserver
{
    public function __construct(protected AccountingService $accounting) {}

    public function created(ClinicStockMovement $movement): void
    {
        match ($movement->type) {
            'consume' => $this->accounting->recordStockConsume($movement),
            'restock' => $this->accounting->recordStockRestock($movement),
            default => null,
        };
    }
}
