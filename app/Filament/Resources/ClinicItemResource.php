<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicItemResource\Pages;
use App\Models\Branch;
use App\Models\ClinicItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClinicItemResource extends Resource
{
    protected static ?string $model = ClinicItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Clinic — Setup';

    protected static ?string $modelLabel = 'Clinic Item';

    protected static ?string $pluralModelLabel = 'Clinic Items';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Item Details')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn () => Branch::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                            ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Leave empty for a shared item usable in all branches.'),

                    Forms\Components\Select::make('type')
                        ->label('Type')
                        ->options([
                            'consumable' => 'Consumable',
                            'service' => 'Service',
                        ])
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            // Sensible default: consumables are usually stockable; services are not
                            if ($state === 'consumable') {
                                $set('is_stockable', true);
                            } else {
                                $set('is_stockable', false);
                                $set('stock_unit', null);
                                $set('usage_unit', null);
                                $set('conversion_factor', null);
                                $set('consume_step', null);
                            }
                        }),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\TextInput::make('name.en')
                        ->label('Name (EN)')
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('name.ar')
                        ->label('Name (AR)')
                        ->required()
                        ->maxLength(191),
                ]),

            Forms\Components\Section::make('Inventory & Units')
                ->description('Configure how this item is stocked and consumed.')
                ->collapsible()
                ->hidden(fn (Get $get) => $get('type') !== 'consumable')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_stockable')
                        ->label('Track Stock')
                        ->helperText('Enable strict inventory tracking for this consumable.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('stock_unit')
                        ->label('Stock Unit')
                        ->placeholder('e.g. Box, Vial')
                        ->helperText('How you buy it from suppliers.')
                        ->required(fn (Get $get) => (bool) $get('is_stockable'))
                        ->maxLength(50)
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\TextInput::make('usage_unit')
                        ->label('Usage Unit (Base)')
                        ->placeholder('e.g. Tablet, ml, Unit')
                        ->helperText('Doctors consume this unit. Cost/price are per this unit.')
                        ->required(fn (Get $get) => (bool) $get('is_stockable'))
                        ->maxLength(50)
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\TextInput::make('conversion_factor')
                        ->label('Conversion Factor')
                        ->numeric()
                        ->step(0.0001)
                        ->helperText('How many Usage Units in 1 Stock Unit? (e.g. 1 Box = 50 Tablets)')
                        ->required(fn (Get $get) => (bool) $get('is_stockable'))
                        ->minValue(0.0001)
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\TextInput::make('consume_step')
                        ->label('Consumption Step')
                        ->numeric()
                        ->step(0.0001)
                        ->default(1)
                        ->minValue(0.0001)
                        ->helperText('Recommended increment step for usage (e.g. 0.5 for ml).')
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\Toggle::make('is_billable')
                        ->label('Billable to Patient')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Pricing (Per Usage Unit)')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('default_cost')
                        ->label('Default Cost')
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->required()
                        ->helperText('Cost per Usage Unit (e.g. cost per ml / unit).'),

                    Forms\Components\TextInput::make('default_price')
                        ->label('Default Price')
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->required()
                        ->helperText('Price per Usage Unit.'),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return 'Shared';
                        }
                        $b = Branch::query()->find($state);

                        return $b?->localized_name ?? ('#'.$state);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn ($state, ClinicItem $r) => $r->localized_name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')->badge(),

                Tables\Columns\IconColumn::make('is_stockable')
                    ->label('Stock?')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('usage_unit')
                    ->label('Unit')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('default_cost')->label('Cost')->numeric(3),
                Tables\Columns\TextColumn::make('default_price')->label('Price')->numeric(3),

                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                        ->all()
                    ),

                Tables\Filters\SelectFilter::make('type')
                    ->options(['consumable' => 'Consumable', 'service' => 'Service']),

                Tables\Filters\TernaryFilter::make('is_stockable')
                    ->label('Stockable'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinicItems::route('/'),
            'create' => Pages\CreateClinicItem::route('/create'),
            'edit' => Pages\EditClinicItem::route('/{record}/edit'),
        ];
    }
}
