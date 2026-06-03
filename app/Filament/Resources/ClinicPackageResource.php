<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicPackageResource\Pages;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClinicPackageResource extends Resource
{
    protected static ?string $model = ClinicPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.clinic_package.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.clinic_package.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.clinic_package.label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('clinic_inventory.clinic_package.sections.package'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label(__('clinic_inventory.clinic_package.fields.branch'))
                        ->nullable()
                        ->options(fn () => Branch::query()->orderBy('id')->get()
                            ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                            ->all()
                        )
                        ->helperText(__('clinic_inventory.clinic_package.helpers.branch')),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('clinic_inventory.clinic_package.fields.active'))
                        ->default(true),

                    Forms\Components\TextInput::make('name.en')
                        ->label(__('clinic_inventory.clinic_package.fields.name_en'))
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('name.ar')
                        ->label(__('clinic_inventory.clinic_package.fields.name_ar'))
                        ->required()
                        ->maxLength(191),

                    Forms\Components\TextInput::make('default_price')
                        ->label(__('clinic_inventory.clinic_package.fields.default_price'))
                        ->numeric()
                        ->step('0.001')
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                ]),

            Forms\Components\Section::make(__('clinic_inventory.clinic_package.sections.package_items'))
                ->description(__('clinic_inventory.clinic_package.sections.package_items_description'))
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->reorderable(false)
                        ->columns(3)
                        ->schema([
                            Forms\Components\Select::make('clinic_item_id')
                                ->label(__('clinic_inventory.clinic_package.fields.clinic_item'))
                                ->required()
                                ->searchable()
                                ->preload()
                                // A package line can be a service, product or
                                // consumable — show the type and list services
                                // first so they are not buried under stock items.
                                ->options(function () {
                                    $priority = ['service' => 0, 'product' => 1, 'consumable' => 2];
                                    $typeLabel = fn (string $t) => match ($t) {
                                        'service' => __('clinic_inventory.clinic_item.types.service'),
                                        'product' => __('clinic_inventory.clinic_item.types.product'),
                                        'consumable' => __('clinic_inventory.clinic_item.types.consumable'),
                                        default => $t,
                                    };

                                    return ClinicItem::query()
                                        ->where('is_active', 1)
                                        ->get()
                                        ->sortBy(fn (ClinicItem $it) => sprintf('%d|%s', $priority[$it->type] ?? 9, $it->localized_name))
                                        ->mapWithKeys(fn (ClinicItem $it) => [
                                            $it->id => $it->localized_name.' — '.$typeLabel($it->type),
                                        ])
                                        ->all();
                                }),

                            Forms\Components\TextInput::make('qty_base')
                                ->label(__('clinic_inventory.clinic_package.fields.qty_base'))
                                ->numeric()
                                ->step('0.0001')
                                ->minValue(0.0001)
                                ->required(),

                            Forms\Components\Toggle::make('is_consumable')
                                ->label(__('clinic_inventory.clinic_package.fields.consumable'))
                                ->default(true)
                                ->helperText(__('clinic_inventory.clinic_package.helpers.consumable')),
                        ])
                        ->minItems(0)
                        ->addActionLabel(__('clinic_inventory.clinic_package.actions.add_item')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label(__('clinic_inventory.clinic_package.fields.branch'))
                    ->formatStateUsing(fn ($state) => $state
                        ? (Branch::query()->find($state)?->localized_name ?? ('#'.$state))
                        : __('clinic_inventory.clinic_package.global')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('localized_name')
                    ->label(__('clinic_inventory.clinic_package.fields.name'))
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('clinic_inventory.clinic_package.fields.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('default_price')
                    ->label(__('clinic_inventory.clinic_package.fields.price'))
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label(__('clinic_inventory.clinic_package.fields.items'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('clinic_inventory.clinic_package.fields.branch'))
                    ->options(fn () => Branch::query()->orderBy('id')->get()
                        ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                        ->all()
                    ),

                Tables\Filters\TernaryFilter::make('is_active')->label(__('clinic_inventory.clinic_package.fields.active')),
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
            'index' => Pages\ListClinicPackages::route('/'),
            'create' => Pages\CreateClinicPackage::route('/create'),
            'edit' => Pages\EditClinicPackage::route('/{record}/edit'),
        ];
    }
}
