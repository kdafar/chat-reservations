<?php

namespace App\Filament\Resources\FollowUpPlanResource\Pages;

use App\Filament\Resources\FollowUpPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFollowUpPlan extends EditRecord
{
    protected static string $resource = FollowUpPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
