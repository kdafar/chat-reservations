<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\BranchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListBranches extends ListRecords
{
    use HasHelpAction;
    use Translatable;

    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
            Actions\LocaleSwitcher::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_branches.what.heading'), 'body' => __('help.pages.list_branches.what.body')],
            ['heading' => __('help.pages.list_branches.how.heading'), 'items' => (array) trans('help.pages.list_branches.how.items')],
            ['heading' => __('help.pages.list_branches.faq.heading'), 'items' => (array) trans('help.pages.list_branches.faq.items')],
        ];
    }
}
