<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\VisitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisits extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = VisitResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make()
                // FIX: Restrict manual creation to Admins only.
                // Receptionists must use 'Check-in' on Bookings to ensure proper logic.
                ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_visits.what.heading'), 'body' => __('help.pages.list_visits.what.body')],
            ['heading' => __('help.pages.list_visits.how.heading'), 'items' => (array) trans('help.pages.list_visits.how.items')],
            ['heading' => __('help.pages.list_visits.faq.heading'), 'items' => (array) trans('help.pages.list_visits.faq.items')],
        ];
    }
}
