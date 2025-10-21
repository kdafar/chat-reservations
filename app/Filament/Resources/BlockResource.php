<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockResource\Pages;
use App\Models\Block;
use App\Models\City;
use App\Models\State;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // <-- 1. Make the resource translatable
use Illuminate\Validation\Rule;

class BlockResource extends Resource
{
    use Translatable; // <-- 1. Make the resource translatable

    protected static ?string $model = Block::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Locations';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                Select::make('state_id')
                    ->label('State')
                    // 2. Updated State dropdown to be translatable
                    ->options(fn () => State::query()
                        ->orderBy('name->'.app()->getLocale())
                        ->get()
                        ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                    ->dehydrated(false)
                    ->columnSpan(6),

                Select::make('city_id')
                    ->label('City')
                    // 3. Updated City dropdown to be translatable
                    ->options(fn (Get $get) => City::query()
                        ->where('state_id', $get('state_id'))
                        ->orderBy('name->'.app()->getLocale())
                        ->get()
                        ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->columnSpan(6),

                // 4. Made the 'Name' input translatable
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(120)
                    ->columnSpan(6),

                TextInput::make('code')
                    ->label('Block Code')
                    ->helperText('Numeric/string code unique within the selected city (e.g., 1, 2, 3).')
                    ->required()
                    ->maxLength(20)
                    ->rule(function (Get $get) {
                        return Rule::unique('blocks', 'code')
                            ->where(fn ($q) => $q->where('city_id', $get('city_id')))
                            ->ignore(request()->route('record'));
                    })
                    ->columnSpan(6),
                TextInput::make('latitude')
                    ->label('Latitude')
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90)
                    ->nullable()
                    ->columnSpan(6),

                TextInput::make('longitude')
                    ->label('Longitude')
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180)
                    ->nullable()
                    ->columnSpan(6),

                Toggle::make('is_active')->label('Active')->default(true)->columnSpan(12),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 5. Consolidated and updated table columns to be translatable
                TextColumn::make('city.state.name')
                    ->label('State')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('city.state', fn (Builder $q) => $q->where('name->'.app()->getLocale(), 'like', "%{$search}%"));
                    })
                    ->sortable(),

                TextColumn::make('city.name')
                    ->label('City')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('city', fn (Builder $q) => $q->where('name->'.app()->getLocale(), 'like', "%{$search}%"));
                    })
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Block')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('name->'.app()->getLocale(), 'like', "%{$search}%");
                    }),

                TextColumn::make('code')->label('Code')->sortable()->searchable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                // 6. Updated the City filter to be translatable
                Tables\Filters\SelectFilter::make('city_id')
                    ->label('City')
                    ->relationship('city', 'name->'.app()->getLocale())
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
            'index' => Pages\ListBlocks::route('/'),
            'create' => Pages\CreateBlock::route('/create'),
            'edit' => Pages\EditBlock::route('/{record}/edit'),
        ];
    }
}
