<?php

namespace App\Filament\Resources\ModifierGroupResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\Concerns\Translatable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OptionsRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'options';

    protected static ?string $title = 'Options';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            // Replaced separate fields with a single translatable field
            TextInput::make('name')
                ->label('Name')
                ->required(),

            TextInput::make('price_delta')->numeric()->step('0.001')->default(0),
            Toggle::make('is_default'),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            // The 'name' column now automatically uses the current locale
            TextColumn::make('name')
                ->label('Option')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    // Custom search for the translated field
                    return $query->where('name->'.$this->getActiveLocale(), 'like', "%{$search}%");
                })
                ->sortable(),

            TextColumn::make('price_delta')->money('kwd', true),
            IconColumn::make('is_default')->boolean(),
        ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                // Added a LocaleSwitcher to the table header
                Tables\Actions\LocaleSwitcher::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }
}
