<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicItemStockResource\Pages;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Services\Clinic\ClinicStockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClinicItemStockResource extends Resource
{
    protected static ?string $model = ClinicItemStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Clinic — Inventory';

    protected static ?string $modelLabel = 'Clinic Stock';

    protected static ?string $pluralModelLabel = 'Clinic Stocks';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Stock (Base Units)')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn () => Branch::query()->orderBy('id')->get()
                            ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                            ->all()
                        )
                        ->required()
                        ->disabled(fn (?ClinicItemStock $record) => filled($record)),

                    Forms\Components\Select::make('clinic_item_id')
                        ->label('Clinic Item')
                        ->options(fn () => ClinicItem::query()->orderBy('id', 'desc')->get()
                            ->mapWithKeys(fn (ClinicItem $it) => [$it->id => $it->localized_name])
                            ->all()
                        )
                        ->required()
                        ->disabled(fn (?ClinicItemStock $record) => filled($record)),

                    Forms\Components\TextInput::make('qty_on_hand_base')
                        ->label('Qty On Hand (Base)')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('min_qty_threshold_base')
                        ->label('Low Stock Threshold (Base)')
                        ->numeric()
                        ->step('0.0001')
                        ->nullable(),

                    Forms\Components\TextInput::make('bin_location')
                        ->label('Bin Location')
                        ->maxLength(191)
                        ->nullable(),
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
                    ->formatStateUsing(function ($state) {
                        $b = Branch::query()->find($state);

                        return $b?->localized_name ?? ('#'.$state);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('clinic_item_id')
                    ->label('Item')
                    ->formatStateUsing(fn ($state, ClinicItemStock $r) => $r->clinicItem?->localized_name ?? ('#'.$state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty_on_hand_base')
                    ->label('On Hand (Base)')
                    ->numeric(4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('min_qty_threshold_base')
                    ->label('Threshold')
                    ->numeric(4)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bin_location')
                    ->label('Bin')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => Branch::query()->orderBy('id')->get()
                        ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                        ->all()
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('receiveStock')
                    ->label('Receive Stock')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->required()
                            ->options(fn () => Branch::query()->orderBy('id')->get()
                                ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                                ->all()
                            ),

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

                        Forms\Components\TextInput::make('qty_stock_units')
                            ->label('Qty (Stock Units)')
                            ->numeric()
                            ->step('0.0001')
                            ->nullable()
                            ->helperText('Enter boxes/vials/bottles count. Will convert to base using conversion_factor.'),

                        Forms\Components\TextInput::make('qty_base')
                            ->label('Qty (Base Units)')
                            ->numeric()
                            ->step('0.0001')
                            ->nullable()
                            ->helperText('Alternatively enter base qty directly (ml/units/pcs).'),

                        Forms\Components\TextInput::make('notes')
                            ->label('Notes')
                            ->maxLength(191)
                            ->nullable(),
                    ])
                    ->action(function (array $data) {
                        $branchId = (int) $data['branch_id'];
                        $itemId = (int) $data['clinic_item_id'];

                        $qtyStockUnits = isset($data['qty_stock_units']) && $data['qty_stock_units'] !== ''
                            ? (float) $data['qty_stock_units']
                            : null;

                        $qtyBase = isset($data['qty_base']) && $data['qty_base'] !== ''
                            ? (float) $data['qty_base']
                            : null;

                        if (($qtyStockUnits === null || $qtyStockUnits <= 0) && ($qtyBase === null || $qtyBase <= 0)) {
                            Notification::make()
                                ->title('Please enter Qty (Stock Units) or Qty (Base Units)')
                                ->danger()
                                ->send();

                            return;
                        }

                        $item = ClinicItem::query()->findOrFail($itemId);

                        app(ClinicStockService::class)->restock(
                            branchId: $branchId,
                            item: $item,
                            qtyStockUnits: $qtyStockUnits,
                            qtyBase: $qtyBase,
                            performedBy: (int) (auth()->id() ?? 0),
                            notes: $data['notes'] ?? null,
                            related: null,
                        );

                        Notification::make()
                            ->title('Stock received successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListClinicItemStocks::route('/'),
            'create' => Pages\CreateClinicItemStock::route('/create'),
            'edit' => Pages\EditClinicItemStock::route('/{record}/edit'),
        ];
    }
}
