<?php

namespace App\Filament\Resources\DoctorResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CompensationProfileRelationManager extends RelationManager
{
    protected static string $relationship = 'compensationProfile';

    protected static ?string $title = 'Compensation Profile';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label('Type')
                ->options([
                    'salary' => 'Salary',
                    'percentage' => 'Percentage',
                ])
                ->native(false)
                ->required()
                ->reactive(),

            Forms\Components\Select::make('basis')
                ->label('Basis')
                ->options([
                    'fees_only' => 'Fees Only (Fees - Discount)',
                    'net_profit' => 'Net Profit',
                ])
                ->native(false)
                ->required()
                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'percentage') === 'percentage'),

            Forms\Components\TextInput::make('percentage_rate')
                ->label('Percentage Rate')
                ->numeric()
                ->step('0.001')
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->nullable()
                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'percentage') === 'percentage')
                ->helperText('Example: 30.000'),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('basis')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('percentage_rate')->label('Rate')->suffix('%')->numeric(3),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('Y-m-d h:i A')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                // allow creating only if none exists (since doctor_id is unique)
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()?->compensationProfile === null),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]) // no bulk actions for a hasOne
            ->emptyStateHeading('No compensation profile yet')
            ->emptyStateDescription('Create a profile to define doctor payout rules.');
    }
}
