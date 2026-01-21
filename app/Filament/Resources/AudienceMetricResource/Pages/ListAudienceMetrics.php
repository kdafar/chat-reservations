<?php

namespace App\Filament\Resources\AudienceMetricResource\Pages;

use App\Filament\Resources\AudienceMetricResource;
use App\Jobs\SendCampaignInvite;
use App\Models\BulkInviteCampaign;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ListAudienceMetrics extends ListRecords
{
    protected static string $resource = AudienceMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // New: go straight to your existing Campaign create page
            Actions\Action::make('goToCampaignCreate')
                ->label('Create Campaign')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->url(\App\Filament\Resources\BulkInviteCampaignResource::getUrl('create'))
                ->openUrlInNewTab(),

            // Keep: add the results to an existing campaign
            Actions\Action::make('addToCampaign')
                ->label('Add Results to Campaign')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('campaign_id')
                        ->label('Select Campaign')
                        ->options(fn () => BulkInviteCampaign::query()->orderByDesc('id')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('default_locale')
                        ->label('Default Locale for new recipients')
                        ->options(['en' => 'English', 'ar' => 'العربية'])
                        ->default(fn () => config('app.locale', 'en'))
                        ->required(),

                    Forms\Components\Toggle::make('dedupe')
                        ->label('De-duplicate by phone within campaign')
                        ->default(true),

                    Forms\Components\Toggle::make('queue_now')
                        ->label('Queue send jobs now')
                        ->default(false)
                        ->helperText('If ON, all newly added recipients will be queued immediately.'),
                ])
                ->action(function (array $data) {
                    $campaign = BulkInviteCampaign::find($data['campaign_id']);
                    if (! $campaign) {
                        Notification::make()->title('Missing campaign')->danger()->send();

                        return;
                    }

                    $msisdns = $this->getFilteredTableQuery()->clone()->pluck('msisdn')->toArray();
                    if (empty($msisdns)) {
                        Notification::make()->title('No results')->body('Your current filters returned 0 contacts.')->danger()->send();

                        return;
                    }

                    $rows = [];
                    foreach ($msisdns as $msisdn) {
                        $rows[] = [
                            'bulk_invite_campaign_id' => $campaign->id,
                            'msisdn' => $msisdn,
                            'name' => null,
                            'locale' => $data['default_locale'],
                            'source' => 'system',
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    DB::transaction(function () use ($rows, $campaign, $data) {
                        DB::table('bulk_invite_recipients')->upsert(
                            $rows,
                            ['bulk_invite_campaign_id', 'msisdn'],
                            ['name', 'locale', 'source', 'status', 'updated_at']
                        );

                        if ($data['queue_now']) {
                            $campaign->status = $campaign->scheduled_at ? 'scheduled' : 'running';
                            $campaign->save();

                            $ids = $campaign->recipients()
                                ->whereIn('msisdn', Arr::pluck($rows, 'msisdn'))
                                ->pluck('id');

                            foreach ($ids as $rid) {
                                dispatch(new SendCampaignInvite($campaign->id, $rid));
                            }
                        }
                    });

                    Notification::make()
                        ->title('Recipients added')
                        ->body('Audience results added to the selected campaign'.($data['queue_now'] ? ' and queued.' : '.'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
