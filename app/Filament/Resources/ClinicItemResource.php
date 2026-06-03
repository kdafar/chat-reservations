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

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.clinic_item.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.clinic_item.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.clinic_item.label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('clinic_inventory.clinic_item.sections.item_details'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label(__('clinic_inventory.clinic_item.fields.branch'))
                        ->options(fn () => Branch::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                            ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText(__('clinic_inventory.clinic_item.helpers.branch')),

                    Forms\Components\Select::make('type')
                        ->label(__('clinic_inventory.clinic_item.fields.type'))
                        ->options([
                            'consumable' => __('clinic_inventory.clinic_item.types.consumable'),
                            'service' => __('clinic_inventory.clinic_item.types.service'),
                            'product' => __('clinic_inventory.clinic_item.types.product'),
                        ])
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            // Services never hold stock; consumables and products do.
                            if ($state === 'service') {
                                $set('is_stockable', false);
                                $set('stock_unit', null);
                                $set('usage_unit', null);
                                $set('conversion_factor', null);
                                $set('consume_step', null);
                            } else {
                                $set('is_stockable', true);
                            }
                        }),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('clinic_inventory.clinic_item.fields.active'))
                        ->default(true),

                    Forms\Components\TextInput::make('name.en')
                        ->label(__('clinic_inventory.clinic_item.fields.name_en'))
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('name.ar')
                        ->label(__('clinic_inventory.clinic_item.fields.name_ar'))
                        ->required()
                        ->maxLength(191),
                ]),

            Forms\Components\Section::make(__('clinic_inventory.clinic_item.sections.inventory_units'))
                ->description(__('clinic_inventory.clinic_item.sections.inventory_units_description'))
                ->collapsible()
                ->hidden(fn (Get $get) => $get('type') === 'service')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_stockable')
                        ->label(__('clinic_inventory.clinic_item.fields.track_stock'))
                        ->helperText(__('clinic_inventory.clinic_item.helpers.track_stock'))
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('stock_unit')
                        ->label(__('clinic_inventory.clinic_item.fields.stock_unit'))
                        ->placeholder(__('clinic_inventory.clinic_item.placeholders.stock_unit'))
                        ->helperText(__('clinic_inventory.clinic_item.helpers.stock_unit'))
                        ->required(fn (Get $get) => (bool) $get('is_stockable'))
                        ->maxLength(50)
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\TextInput::make('usage_unit')
                        ->label(__('clinic_inventory.clinic_item.fields.usage_unit'))
                        ->placeholder(__('clinic_inventory.clinic_item.placeholders.usage_unit'))
                        ->helperText(__('clinic_inventory.clinic_item.helpers.usage_unit'))
                        ->required(fn (Get $get) => (bool) $get('is_stockable'))
                        ->maxLength(50)
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\TextInput::make('conversion_factor')
                        ->label(__('clinic_inventory.clinic_item.fields.conversion_factor'))
                        ->numeric()
                        ->step(0.0001)
                        ->helperText(__('clinic_inventory.clinic_item.helpers.conversion_factor'))
                        ->required(fn (Get $get) => (bool) $get('is_stockable'))
                        ->minValue(0.0001)
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\TextInput::make('consume_step')
                        ->label(__('clinic_inventory.clinic_item.fields.consume_step'))
                        ->numeric()
                        ->step(0.0001)
                        ->default(1)
                        ->minValue(0.0001)
                        ->helperText(__('clinic_inventory.clinic_item.helpers.consume_step'))
                        ->disabled(fn (Get $get) => ! (bool) $get('is_stockable')),

                    Forms\Components\Toggle::make('is_billable')
                        ->label(__('clinic_inventory.clinic_item.fields.is_billable'))
                        ->default(true),
                ]),

            // Service bill of materials — the consumables a service uses, which
            // auto-deduct from stock when the service is performed on a visit.
            Forms\Components\Section::make(__('clinic_inventory.clinic_item.sections.bom'))
                ->description(__('clinic_inventory.clinic_item.sections.bom_description'))
                ->visible(fn (Get $get) => $get('type') === 'service')
                ->schema([
                    Forms\Components\Repeater::make('components')
                        ->relationship('components')
                        ->label('')
                        ->addActionLabel(__('clinic_inventory.clinic_item.bom.add'))
                        ->columns(3)
                        ->schema([
                            Forms\Components\Select::make('component_item_id')
                                ->label(__('clinic_inventory.clinic_item.bom.item'))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->options(fn () => ClinicItem::query()
                                    ->where('is_active', 1)
                                    ->where('type', '!=', 'service')
                                    ->get()
                                    ->sortBy(fn (ClinicItem $it) => $it->localized_name)
                                    ->mapWithKeys(fn (ClinicItem $it) => [$it->id => $it->localized_name])
                                    ->all()
                                ),

                            Forms\Components\TextInput::make('qty_base')
                                ->label(__('clinic_inventory.clinic_item.bom.qty_base'))
                                ->numeric()
                                ->step('0.0001')
                                ->minValue(0.0001)
                                ->default(1)
                                ->required(),

                            Forms\Components\Toggle::make('is_optional')
                                ->label(__('clinic_inventory.clinic_item.bom.is_optional'))
                                ->helperText(__('clinic_inventory.clinic_item.bom.is_optional_help'))
                                ->default(false),
                        ]),
                ]),

            Forms\Components\Section::make(__('clinic_inventory.clinic_item.sections.pricing'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('default_cost')
                        ->label(__('clinic_inventory.clinic_item.fields.default_cost'))
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->required()
                        ->helperText(__('clinic_inventory.clinic_item.helpers.default_cost')),

                    Forms\Components\TextInput::make('default_price')
                        ->label(__('clinic_inventory.clinic_item.fields.default_price'))
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->required()
                        ->helperText(__('clinic_inventory.clinic_item.helpers.default_price')),
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
                    ->label(__('clinic_inventory.clinic_item.fields.branch'))
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return __('clinic_inventory.clinic_item.shared');
                        }
                        $b = Branch::query()->find($state);

                        return $b?->localized_name ?? ('#'.$state);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('clinic_inventory.clinic_item.fields.name'))
                    ->formatStateUsing(fn ($state, ClinicItem $r) => $r->localized_name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('clinic_inventory.clinic_item.fields.type'))
                    ->formatStateUsing(fn (string $state): string => $state ? __('clinic_inventory.clinic_item.types.'.$state) : '')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_stockable')
                    ->label(__('clinic_inventory.clinic_item.fields.stock_q'))
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('usage_unit')
                    ->label(__('clinic_inventory.clinic_item.fields.unit'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('default_cost')->label(__('clinic_inventory.clinic_item.fields.cost'))->numeric(3),
                Tables\Columns\TextColumn::make('default_price')->label(__('clinic_inventory.clinic_item.fields.price'))->numeric(3),

                Tables\Columns\IconColumn::make('is_active')->label(__('clinic_inventory.clinic_item.fields.active'))->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('clinic_inventory.clinic_item.fields.branch'))
                    ->options(fn () => Branch::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                        ->all()
                    ),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('clinic_inventory.clinic_item.fields.type'))
                    ->options([
                        'consumable' => __('clinic_inventory.clinic_item.types.consumable'),
                        'service' => __('clinic_inventory.clinic_item.types.service'),
                        'product' => __('clinic_inventory.clinic_item.types.product'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_stockable')
                    ->label(__('clinic_inventory.clinic_item.filter_stockable')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading(__('resources.clinic_item.empty_heading'))
            ->emptyStateDescription(__('resources.clinic_item.empty_description'))
            ->emptyStateIcon('heroicon-o-beaker')
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
