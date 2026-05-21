<?php

namespace App\Filament\Resources\BranchBlackoutResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\BranchBlackoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBranchBlackouts extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = BranchBlackoutResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_branch_blackouts.what.heading'), 'body' => __('help.pages.list_branch_blackouts.what.body')],
            ['heading' => __('help.pages.list_branch_blackouts.how.heading'), 'items' => (array) trans('help.pages.list_branch_blackouts.how.items')],
            ['heading' => __('help.pages.list_branch_blackouts.faq.heading'), 'items' => (array) trans('help.pages.list_branch_blackouts.faq.items')],
        ];
    }
}
