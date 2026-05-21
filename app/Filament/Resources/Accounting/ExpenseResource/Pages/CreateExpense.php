<?php

namespace App\Filament\Resources\Accounting\ExpenseResource\Pages;

use App\Filament\Resources\Accounting\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;
}
