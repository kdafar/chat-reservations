<?php

namespace App\Filament\Resources\StaffAttendanceResource\Pages;

use App\Filament\Resources\StaffAttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffAttendance extends CreateRecord
{
    protected static string $resource = StaffAttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
        return $data;
    }
}
