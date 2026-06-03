<?php

namespace App\Filament\Resources\StaffLeaveResource\Pages;

use App\Filament\Resources\StaffLeaveResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffLeave extends CreateRecord
{
    protected static string $resource = StaffLeaveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
        return $data;
    }
}
