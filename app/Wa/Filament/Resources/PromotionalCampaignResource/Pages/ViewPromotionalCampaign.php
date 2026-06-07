<?php

namespace App\Wa\Filament\Resources\PromotionalCampaignResource\Pages;

use App\Wa\Filament\Resources\PromotionalCampaignResource;
use App\Wa\Hub\Models\CampaignConversion;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ViewPromotionalCampaign extends ViewRecord
{
    protected static string $resource = PromotionalCampaignResource::class;

    /**
     * The access level for this method must be public to override the parent class method.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Campaign Details')
                    ->schema([
                        TextEntry::make('name'),
                        // Use a formatter to correctly display the translated restaurant name
                        TextEntry::make('restaurant.name')->formatStateUsing(fn ($record) => $record->restaurant?->getTranslation('name', 'en')),
                        TextEntry::make('messageTemplate.name')->label('Template Used'),
                        TextEntry::make('status')->badge()->color(fn (string $state): string => match ($state) {
                            'draft' => 'gray',
                            'sending' => 'warning',
                            'completed' => 'success',
                            'failed' => 'danger',
                        }),
                        TextEntry::make('sent_at')->dateTime(),
                    ])->columns(2),
                Section::make('Performance Analytics')
                    ->schema([
                        TextEntry::make('total_recipients')->label('Recipients'),
                        TextEntry::make('conversions_count')->label('Conversions'),
                        TextEntry::make('conversion_rate')
                            ->label('Conversion Rate')
                            ->formatStateUsing(function ($record) {
                                if ($record->total_recipients === 0) {
                                    return '0%';
                                }
                                // Use the relationship to get the count for accuracy
                                $rate = ($record->conversions()->count() / $record->total_recipients) * 100;

                                return number_format($rate, 2).'%';
                            }),
                    ])->columns(3),
            ]);
    }

    public function conversionsTable(Table $table): Table
    {
        return $table
            ->query(CampaignConversion::query()->where('promotional_campaign_id', $this->record->id)->with(['session.customerProfile']))
            ->heading('Successful Conversions')
            ->columns([
                TextColumn::make('session.customerProfile.full_name')
                    ->label('Customer Name')
                    ->default('N/A'),
                TextColumn::make('session.customer_phone_number')
                    ->label('Customer Phone'),
                TextColumn::make('order_id_from_restaurant')
                    ->label('Resulting Order ID'),
                TextColumn::make('created_at')
                    ->label('Conversion Date')
                    ->dateTime(),
            ])
            ->paginated();
    }
}
