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

    protected static ?string $navigationGroup = 'Clinic — Setup';

    protected static ?string $modelLabel = 'Package';

    protected static ?string $pluralModelLabel = 'Packages';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Package')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->nullable()
                        ->options(fn () => Branch::query()->orderBy('id')->get()
                            ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                            ->all()
                        )
                        ->helperText('Leave empty to make it global (available for all branches).'),

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

                    Forms\Components\TextInput::make('default_price')
                        ->label('Default Price')
                        ->numeric()
                        ->step('0.001')
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                ]),

            Forms\Components\Section::make('Package Items')
                ->description('Define required clinic items (base qty). These are used to build the stock request when doctor selects the package.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->reorderable(false)
                        ->columns(3)
                        ->schema([
                            Forms\Components\Select::make('clinic_item_id')
                                ->label('Clinic Item')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->options(fn () => ClinicItem::query()
                                    ->where('is_active', 1)
                                    ->orderBy('id', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn (ClinicItem $it) => [$it->id => $it->localized_name])
                                    ->all()
                                ),

                            Forms\Components\TextInput::make('qty_base')
                                ->label('Qty (Base)')
                                ->numeric()
                                ->step('0.0001')
                                ->minValue(0.0001)
                                ->required(),

                            Forms\Components\Toggle::make('is_consumable')
                                ->label('Consumable')
                                ->default(true)
                                ->helperText('If false, it is “non-consumable” and can be informational only.'),
                        ])
                        ->minItems(0)
                        ->addActionLabel('Add item'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch')
                    ->formatStateUsing(fn ($state) => $state
                        ? (Branch::query()->find($state)?->localized_name ?? ('#'.$state))
                        : 'Global'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('localized_name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('default_price')
                    ->label('Price')
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->orderBy('id')->get()
                        ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                        ->all()
                    ),

                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
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
