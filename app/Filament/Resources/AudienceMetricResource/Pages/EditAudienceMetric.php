<?php

namespace App\Filament\Resources\AudienceMetricResource\Pages;

use App\Filament\Resources\AudienceMetricResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAudienceMetric extends EditRecord
{
    protected static string $resource = AudienceMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
