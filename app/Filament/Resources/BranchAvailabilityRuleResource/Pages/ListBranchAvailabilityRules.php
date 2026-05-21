<?php

namespace App\Filament\Resources\BranchAvailabilityRuleResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\BranchAvailabilityRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBranchAvailabilityRules extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = BranchAvailabilityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([
            Actions\CreateAction::make(),
        ]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_branch_availability_rules.what.heading'), 'body' => __('help.pages.list_branch_availability_rules.what.body')],
            ['heading' => __('help.pages.list_branch_availability_rules.how.heading'), 'items' => (array) trans('help.pages.list_branch_availability_rules.how.items')],
            ['heading' => __('help.pages.list_branch_availability_rules.faq.heading'), 'items' => (array) trans('help.pages.list_branch_availability_rules.faq.items')],
        ];
    }
}
