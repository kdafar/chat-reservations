<?php

namespace App\Filament\Resources\Insurance\InsurerResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PlansRelationManager extends RelationManager
{
    protected static string $relationship = 'plans';

    protected static ?string $title = 'Plans';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(32),

            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(191),

            Forms\Components\TextInput::make('name_ar')
                ->label('Name (Arabic)')
                ->maxLength(191),

            Forms\Components\Select::make('tier')
                ->options([
                    'platinum' => 'Platinum',
                    'gold' => 'Gold',
                    'silver' => 'Silver',
                    'bronze' => 'Bronze',
                ])
                ->nullable(),

            Forms\Components\DatePicker::make('effective_from')->native(false),

            Forms\Components\DatePicker::make('effective_until')->native(false),

            Forms\Components\Toggle::make('is_active')
                ->default(true),

            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tier')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'platinum' => 'info',
                        'gold' => 'warning',
                        'silver' => 'gray',
                        'bronze' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
