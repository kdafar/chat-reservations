<?php

namespace App\Filament\Resources\HomepageSectionResource\RelationManagers;

use App\Models\City;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class FeaturedCitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'featuredCityLinks'; // HasMany to pivot model

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Select::make('city_id')
                ->label(__('City'))
                ->options(fn () => City::query()
                    ->where('is_active', true)
                    ->orderBy('name->'.app()->getLocale())
                    ->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                    // unique per section
                    return $rule->where('homepage_section_id', $this->ownerRecord->id);
                })
                ->afterStateUpdated(function (Set $set, Get $get) {
                    if (blank($get('sort_order'))) {
                        $set('sort_order', 999);
                    }
                }),

            Forms\Components\TextInput::make('sort_order')
                ->label(__('Order'))
                ->numeric()
                ->default(999)
                ->minValue(0)
                ->hint(__('Drag rows to reorder; this is a fallback')),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->reorderable('sort_order') // drag to reorder
            ->columns([
                TextColumn::make('city.name')->label(__('City'))
                    ->formatStateUsing(fn ($record) => $record->city?->getTranslation('name', app()->getLocale()))
                    ->searchable(),
                TextColumn::make('city.state.name')->label(__('State'))
                    ->formatStateUsing(fn ($record) => optional($record->city?->state)?->getTranslation('name', app()->getLocale())),
                TextColumn::make('sort_order')->label(__('Order'))->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Add City')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }
}
