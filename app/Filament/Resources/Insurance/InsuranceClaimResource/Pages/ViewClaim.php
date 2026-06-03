<?php

namespace App\Filament\Resources\Insurance\InsuranceClaimResource\Pages;

use App\Filament\Resources\Insurance\InsuranceClaimResource;
use App\Models\Insurance\InsuranceClaim;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClaim extends ViewRecord
{
    protected static string $resource = InsuranceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewVisit')
                ->label('View Visit')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn () => $this->record instanceof InsuranceClaim && $this->record->visit_id !== null)
                ->url(fn () => $this->record->visit_id
                    ? route('filament.admin.resources.visits.edit', ['record' => $this->record->visit_id])
                    : null),

            Actions\EditAction::make()
                ->visible(fn () => $this->record instanceof InsuranceClaim
                    && $this->record->status === InsuranceClaim::STATUS_DRAFT),
        ];
    }
}
