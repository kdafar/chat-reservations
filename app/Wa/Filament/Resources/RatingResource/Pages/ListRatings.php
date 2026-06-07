<?php

namespace App\Wa\Filament\Resources\RatingResource\Pages;

use App\Wa\Filament\Resources\RatingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRatings extends ListRecords
{
    protected static string $resource = RatingResource::class;

    //  Tell Filament to use our custom Blade view for this page
    protected static string $view = 'filament.resources.rating-resource.pages.list-ratings';

    public function mount(): void
    {
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
