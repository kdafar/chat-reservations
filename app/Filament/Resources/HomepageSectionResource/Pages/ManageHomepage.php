<?php

namespace App\Filament\Resources\HomepageSectionResource\Pages;

use App\Filament\Resources\HomepageSectionResource;
use App\Models\HomepageSection;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable as ListTranslatable;

class ManageHomepage extends ListRecords
{
    use ListTranslatable;

    protected static string $resource = HomepageSectionResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [Actions\LocaleSwitcher::make()];
        if (HomepageSection::query()->doesntExist()) {
            $actions[] = Actions\CreateAction::make()->label('Create');
        }

        return $actions;
    }
}
