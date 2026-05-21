<?php

namespace App\Filament\Resources\FollowUpPlanResource\Pages;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\FollowUpPlanResource;
use Filament\Resources\Pages\ListRecords;

class ListFollowUpPlans extends ListRecords
{
    use HasHelpAction;

    protected static string $resource = FollowUpPlanResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.list_follow_up_plans.what.heading'), 'body' => __('help.pages.list_follow_up_plans.what.body')],
            ['heading' => __('help.pages.list_follow_up_plans.how.heading'), 'items' => (array) trans('help.pages.list_follow_up_plans.how.items')],
            ['heading' => __('help.pages.list_follow_up_plans.faq.heading'), 'items' => (array) trans('help.pages.list_follow_up_plans.faq.items')],
        ];
    }
}
