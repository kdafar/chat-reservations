<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // This hook runs BEFORE the form is filled with existing data.

        // Get all permission IDs currently attached to the role
        $rolePermissionIds = $this->record->permissions()->pluck('id')->toArray();
        $groupedPermissions = Permission::all()->groupBy(fn ($p) => Str::before($p->name, '.'));

        // For each group, find which of the role's permissions belong to it
        foreach ($groupedPermissions as $groupName => $permissions) {
            $groupPermissionIds = $permissions->pluck('id')->toArray();

            // The state is the intersection of the role's permissions and the group's permissions
            $intersection = array_intersect($rolePermissionIds, $groupPermissionIds);

            // 👇 THIS IS THE FIX 👇
            // We re-index the array to reset its keys to 0, 1, 2...
            $data[$groupName.'_permissions'] = array_values($intersection);
        }

        // You can now remove the dd($data); line
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $allPermissionIds = [];
        foreach ($data as $key => $value) {
            if (Str::endsWith($key, '_permissions')) {

                // 👇 ADD THIS CHECK 👇
                // Only merge the value if it's an array of IDs
                if (is_array($value)) {
                    $allPermissionIds = array_merge($allPermissionIds, $value);
                }

                // We unset the temporary key regardless
                unset($data[$key]);
            }
        }

        parent::handleRecordUpdate($record, $data);
        $record->permissions()->sync($allPermissionIds);

        return $record;
    }
}
