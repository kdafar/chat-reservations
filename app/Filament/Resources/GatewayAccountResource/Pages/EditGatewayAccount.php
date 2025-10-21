<?php

namespace App\Filament\Resources\GatewayAccountResource\Pages;

use App\Filament\Resources\GatewayAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGatewayAccount extends EditRecord
{
    protected static string $resource = GatewayAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['partner_id'] = $data['owner_type'] === 'partner' ? $data['partner_id'] ?? null : null;
        $data['branch_id'] = $data['owner_type'] === 'branch' ? $data['branch_id'] ?? null : null;
        $data['service_id'] = $data['owner_type'] === 'service' ? $data['service_id'] ?? null : null;

        return $data;
    }
}
