<?php

namespace App\Filament\Resources\HomepageSectionResource\Pages;

use App\Filament\Resources\HomepageSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateHomepageSection extends CreateRecord
{
    use Translatable;

    protected static string $resource = HomepageSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
