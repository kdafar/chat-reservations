<?php

namespace App\Wa\Filament\Resources\PromotionalCampaignResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Recipients';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('msisdn')
                ->label('Phone')
                ->required()
                ->maxLength(32),

            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'sent' => 'Sent',
                    'delivered' => 'Delivered',
                    'failed' => 'Failed',
                ])
                ->default('pending'),

            Forms\Components\TextInput::make('wa_message_id')
                ->label('WA Message ID')
                ->maxLength(191),

            Forms\Components\Textarea::make('error_message')
                ->label('Error')
                ->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('msisdn')
                    ->label('Phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => 'pending',
                        'success' => 'sent',
                        'info' => 'delivered',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('wa_message_id')
                    ->label('WA Message ID')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label('Created'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
