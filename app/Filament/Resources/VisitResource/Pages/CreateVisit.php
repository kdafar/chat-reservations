<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use App\Services\Clinic\FollowUpService;
use Filament\Resources\Pages\CreateRecord;

class CreateVisit extends CreateRecord
{
    protected static string $resource = VisitResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function afterCreate(): void
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
}
