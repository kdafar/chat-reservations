<?php

namespace App\Filament\Resources\BranchAvailabilityRuleResource\Pages;

use App\Filament\Resources\BranchAvailabilityRuleResource;
use App\Models\BranchAvailabilityRule;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBranchAvailabilityRule extends CreateRecord
{
    protected static string $resource = BranchAvailabilityRuleResource::class;

    protected function afterCreate(): void
    {
        /** @var \App\Models\BranchAvailabilityRule $created */
        $created = $this->record;

        // Pull extra form state that wasn't dehydrated
        $state = $this->form->getState();
        $targets = collect($state['apply_to_days'] ?? [])
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 0 && $d <= 6 && $d !== (int) $created->day_of_week)
            ->unique();

        if ($targets->isEmpty()) {
            return;
        }

        // Fields to clone
        $payload = [
            'is_open' => (bool) $created->is_open,
            'open_at' => $created->open_at,
            'close_at' => $created->close_at,
            'slot_length_minutes' => (int) $created->slot_length_minutes,
            'slot_step_minutes' => (int) $created->slot_step_minutes,
            'max_party_size' => (int) $created->max_party_size,
            'lead_time_minutes' => (int) $created->lead_time_minutes,
            'capacity_map' => $created->capacity_map,
        ];

        foreach ($targets as $dow) {
            BranchAvailabilityRule::updateOrCreate(
                ['branch_id' => $created->branch_id, 'day_of_week' => $dow],
                $payload + ['day_of_week' => $dow]
            );
        }

        Notification::make()
            ->title('Saved for multiple days')
            ->body('The rule was applied to the selected days for this branch.')
            ->success()
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
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
