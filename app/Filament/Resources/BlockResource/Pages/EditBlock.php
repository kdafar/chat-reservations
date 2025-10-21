<?php

namespace App\Filament\Resources\BlockResource\Pages;

use App\Filament\Resources\BlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditBlock extends EditRecord
{
    use Translatable;

    protected static string $resource = BlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\LocaleSwitcher::make(),
        ];
    }

    protected function mutateDataBeforeFill(array $data): array
    {
        // This hook runs before the form is filled with data.
        // We get the block's city and find its corresponding state_id.
        $city = \App\Models\City::find($data['city_id']);
        if ($city) {
            $data['state_id'] = $city->state_id;
        }

        return $data;
    }
}
