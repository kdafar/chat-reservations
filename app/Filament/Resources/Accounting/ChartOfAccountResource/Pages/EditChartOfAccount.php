<?php

namespace App\Filament\Resources\Accounting\ChartOfAccountResource\Pages;

use App\Filament\Resources\Accounting\ChartOfAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChartOfAccount extends EditRecord
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->getRecord()?->is_system),
        ];
    }
}
