<?php

namespace App\Filament\Partner\Resources\ModifierGroupResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $title = 'Options';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
            Forms\Components\TextInput::make('price_delta')->label(__('Price Delta'))->numeric()->default(0),
            Forms\Components\Toggle::make('is_default')->label(__('Default'))->default(false),
        ])->columns(2);
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable(),
                Tables\Columns\TextColumn::make('price_delta')->label(__('Δ Price'))->money('KWD', locale: 'en'),
                Tables\Columns\IconColumn::make('is_default')->label(__('Default'))->boolean(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
