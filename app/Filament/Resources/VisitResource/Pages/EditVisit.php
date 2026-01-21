<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use App\Services\Clinic\FollowUpService;
use Filament\Resources\Pages\EditRecord;

class EditVisit extends EditRecord
{
    protected static string $resource = VisitResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function afterSave(): void
    {
        $visit = $this->record;

        if (! $visit?->follow_up_date) {
            return;
        }

        $auto = (bool) ($this->data['auto_create_follow_up_booking'] ?? false);

        app(FollowUpService::class)->syncFromVisit(
            $visit,
            $auto,
            (int) (auth()->id() ?? 0),
        );
    }

    public function mount($record): void
    {
        parent::mount($record);

        // Trigger when opened from Booking deep link
        if (request()->has('activeRelationManager') || request()->get('scrollTo') === 'relations') {
            // Browser event (works reliably with window.addEventListener)
            $this->dispatch('filament-scroll-to-relations');
        }
    }
}
