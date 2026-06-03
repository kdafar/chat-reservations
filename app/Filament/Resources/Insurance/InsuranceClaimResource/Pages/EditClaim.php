<?php

namespace App\Filament\Resources\Insurance\InsuranceClaimResource\Pages;

use App\Filament\Resources\Insurance\InsuranceClaimResource;
use App\Models\Insurance\InsuranceClaim;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClaim extends EditRecord
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

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record instanceof InsuranceClaim
                    && $this->record->status === InsuranceClaim::STATUS_DRAFT
                    && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),
        ];
    }
}
