<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $allPermissionIds = [];
        foreach ($data as $key => $value) {
            if (Str::endsWith($key, '_permissions')) {

                // 👇 ADD THIS CHECK 👇
                // Only merge the value if it's an array of IDs
                if (is_array($value)) {
                    $allPermissionIds = array_merge($allPermissionIds, $value);
                }

                unset($data[$key]);
            }
        }

        $role = parent::handleRecordCreation($data);
        $role->permissions()->sync($allPermissionIds);

        return $role;
    }
}
