<?php

namespace App\Filament\Resources\BranchAvailabilityRuleResource\Pages;

use App\Filament\Resources\BranchAvailabilityRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranchAvailabilityRule extends EditRecord
{
    protected static string $resource = BranchAvailabilityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // ui_party_images: ensure NULL when empty
        if (empty($data['ui_party_images']) || (is_array($data['ui_party_images']) && count($data['ui_party_images']) === 0)) {
            $data['ui_party_images'] = null;
        }

        // ui_time_image: ensure NULL when empty
        $uiTime = $data['ui_time_image'] ?? null;
        if (is_array($uiTime)) {
            $hasMeaningful = false;

            foreach (['src', 'file', 'width', 'height', 'aspect_ratio', 'alt_text', 'scale_type'] as $k) {
                if (isset($uiTime[$k]) && $uiTime[$k] !== null && $uiTime[$k] !== '') {
                    $hasMeaningful = true;
                    break;
                }
            }

            if (! $hasMeaningful) {
                $data['ui_time_image'] = null;
            }
        } elseif (empty($uiTime)) {
            $data['ui_time_image'] = null;
        }

        return $data;
    }
}
