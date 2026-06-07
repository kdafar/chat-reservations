<?php

namespace App\Wa\Filament\Resources\PromotionalCampaignResource\Pages;

use App\Wa\Filament\Resources\PromotionalCampaignResource;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\Vendors;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Jobs\SendPromotionalCampaignJob;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CreatePromotionalCampaign extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = PromotionalCampaignResource::class;

    protected function getSteps(): array
    {
        return [
            Wizard\Step::make('Campaign Details')
                ->description('Select the restaurant, name your campaign, and choose a template.')
                ->schema([
                    Select::make('restaurant_id')
                        ->label('Select Restaurant')
                        // Use getTranslation to display the name correctly in the dropdown
                        ->options(Vendors::all()->mapWithKeys(fn ($r) => [$r->id => $r->getTranslation('name', 'en')]))
                        ->required()
                        ->live()
                        ->searchable(),
                    TextInput::make('name')
                        ->label('Campaign Name')
                        ->required(),
                    Select::make('message_template_id')
                        ->label('Message Template')
                        ->options(MessageTemplate::pluck('name', 'id'))
                        ->required(),
                ]),

            Wizard\Step::make('Select Audience')
                ->description('Filter users based on their activity.')
                ->schema([
                    Select::make('inactivity_period')
                        ->label('Inactive For (Days)')
                        ->options([
                            '7' => '7+ days',
                            '14' => '14+ days',
                            '30' => '30+ days',
                            '90' => '90+ days',
                        ])
                        ->live(),

                    Select::make('restaurant_id_filter')
                        ->label('Last Restaurant Ordered From (Optional)')
                        ->options(Vendors::all()->mapWithKeys(fn ($r) => [$r->id => $r->getTranslation('name', 'en')]))
                        ->searchable()
                        ->live(),

                    Placeholder::make('points_check')
                        ->label('Points Check')
                        ->content(function (Get $get) {
                            $restaurantId = $get('restaurant_id');
                            if (! $restaurantId) {
                                return 'Please select a restaurant first.';
                            }
                            $restaurant = Vendors::find($restaurantId);
                            $userCount = $this->getFilteredUsersQuery($get)->count();
                            $pointsNeeded = $userCount;
                            $hasEnoughPoints = $restaurant->points >= $pointsNeeded;

                            $status = $hasEnoughPoints
                                ? '<span style="color: green; font-weight: bold;">Sufficient</span>'
                                : '<span style="color: red; font-weight: bold;">Insufficient</span>';

                            return new HtmlString(
                                "Users Matched: <strong>{$userCount}</strong><br>".
                                "Points Needed: <strong>{$pointsNeeded}</strong> (1 per user)<br>".
                                "Restaurant Balance: <strong>{$restaurant->points}</strong><br>".
                                "Status: {$status}"
                            );
                        }),
                ]),

            Wizard\Step::make('Confirm & Send')
                ->description('Review the details and send the campaign.')
                ->schema([
                    Placeholder::make('summary')
                        ->content(function (Get $get) {
                            $restaurant = Vendors::find($get('restaurant_id'));
                            if (! $restaurant) {
                                return 'N/A';
                            } // Add a check to prevent errors

                            // --- FIX: Use getTranslation() to get the restaurant name ---
                            $restaurantName = $restaurant->getTranslation('name', 'en');
                            $templateName = MessageTemplate::find($get('message_template_id'))?->name ?? 'N/A';
                            $userCount = $this->getFilteredUsersQuery($get)->count();

                            return "You are about to send the '{$templateName}' template to {$userCount} users from {$restaurantName}. This will deduct {$userCount} points.";
                        }),
                ]),
        ];
    }

    private function getFilteredUsersQuery(Get $get): Builder
    {
        $query = WhatsappSession::query()->whereNotNull('last_interacted_at');

        if ($days = $get('inactivity_period')) {
            $query->where('last_interacted_at', '<=', now()->subDays((int) $days));
        }

        if ($restaurantId = $get('restaurant_id_filter')) {
            $query->where('selected_vendor_id', $restaurantId);
        }

        return $query;
    }

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $restaurant = Vendors::find($data['restaurant_id']);
        $userCount = $this->getFilteredUsersQuery(fn ($key) => $data[$key] ?? null)->count();
        $pointsNeeded = $userCount;

        if ($restaurant->points < $pointsNeeded) {
            Notification::make()
                ->title('Insufficient Points')
                ->body("This restaurant does not have enough points to send this campaign. They need {$pointsNeeded} points but only have {$restaurant->points}.")
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $sessionIds = $this->getFilteredUsersQuery(fn ($key) => $data[$key] ?? null)
            ->pluck('id')->toArray();

        $campaign = static::getModel()::create([
            'restaurant_id' => $data['restaurant_id'],
            'name' => $data['name'],
            'message_template_id' => $data['message_template_id'],
            'status' => 'sending',
            'total_recipients' => count($sessionIds),
            'sent_at' => now(),
        ]);

        if (! empty($sessionIds)) {
            SendPromotionalCampaignJob::dispatch($campaign, $sessionIds);
        } else {
            $campaign->update(['status' => 'completed']);
        }

        return $campaign;
    }
}
