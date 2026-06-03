<?php

namespace App\Filament\Resources\StaffLeaveResource\Pages;

use App\Filament\Resources\StaffLeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaffLeave extends EditRecord
{
    protected static string $resource = StaffLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
