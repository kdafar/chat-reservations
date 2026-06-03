<?php

namespace App\Filament\Resources\Inpatient\WardResource\Pages;

use App\Filament\Resources\Inpatient\WardResource;
use App\Models\Branch;
use Filament\Resources\Pages\CreateRecord;

class CreateWard extends CreateRecord
{
    protected static string $resource = WardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-derive partner_id from selected branch — wards inherit clinic ownership.
        $branch = Branch::query()->find($data['branch_id'] ?? null);
        if ($branch) {
            $data['partner_id'] = $branch->partner_id;
        }
        return $data;
    }
}
