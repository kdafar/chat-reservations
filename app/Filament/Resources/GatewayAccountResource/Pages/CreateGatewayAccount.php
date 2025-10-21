<?php

namespace App\Filament\Resources\GatewayAccountResource\Pages;

use App\Filament\Resources\GatewayAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGatewayAccount extends CreateRecord
{
    protected static string $resource = GatewayAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Null-out unrelated owner columns so we don’t leak stale IDs
        $data['partner_id'] = $data['owner_type'] === 'partner' ? $data['partner_id'] ?? null : null;
        $data['branch_id'] = $data['owner_type'] === 'branch' ? $data['branch_id'] ?? null : null;
        $data['service_id'] = $data['owner_type'] === 'service' ? $data['service_id'] ?? null : null;

        return $data;
    }
}
