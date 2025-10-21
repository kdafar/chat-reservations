<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use App\Models\State;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table; // <-- 1. Make the resource translatable
use Illuminate\Database\Eloquent\Builder;

class CityResource extends Resource
{
    use Translatable; // <-- 1. Make the resource translatable

    protected static ?string $model = City::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Locations';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            // 2. Updated the 'State' dropdown to be fully translatable
            Select::make('state_id')
                ->label('State')
                ->relationship('state')
                ->getOptionLabelFromRecordUsing(fn (State $record) => $record->getTranslation('name', app()->getLocale()))
                ->getSearchResultsUsing(function (string $search) {
                    return State::query()
                        ->where('name->'.app()->getLocale(), 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck('name->'.app()->getLocale(), 'id');
                })
                ->searchable()
                ->required(),

            // 3. Added the ->translatable() method to the 'Name' input
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(120),

            TextInput::make('slug')
                ->required()
                ->maxLength(120)
                ->unique(ignoreRecord: true),

            TextInput::make('latitude')->numeric()->rule('between:-90,90')->nullable(),
            TextInput::make('longitude')->numeric()->rule('between:-180,180')->nullable(),

            Toggle::make('is_active')->label('Active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 4. Updated and consolidated table columns to be translatable
                TextColumn::make('state.name')
                    ->label('State')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('state', fn (Builder $q) => $q->where('name->'.app()->getLocale(), 'like', "%{$search}%"));
                    }),

                TextColumn::make('name')
                    ->label('City')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('name->'.app()->getLocale(), 'like', "%{$search}%");
                    }),

                TextColumn::make('slug')->searchable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                // 5. Updated the 'State' filter to be translatable
                Tables\Filters\SelectFilter::make('state_id')
                    ->label('State')
                    ->relationship('state', 'name->'.app()->getLocale())
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
