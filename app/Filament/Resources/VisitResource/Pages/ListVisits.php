<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                // FIX: Restrict manual creation to Admins only.
                // Receptionists must use 'Check-in' on Bookings to ensure proper logic.
                ->visible(fn () => auth()->id() === 1 || (auth()->user()?->hasRole('admin') ?? false)),
        ];
    }
}
