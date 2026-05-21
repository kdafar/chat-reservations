<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicStockMovementResource\Pages;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicStockMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClinicStockMovementResource extends Resource
{
    protected static ?string $model = ClinicStockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.clinic_stock_movement.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.clinic_stock_movement.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.clinic_stock_movement.label_plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.branch'))
                    ->formatStateUsing(function ($state) {
                        $b = Branch::query()->find($state);

                        return $b?->localized_name ?? ('#'.$state);
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clinic_item_id')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.item'))
                    ->formatStateUsing(function ($state) {
                        $it = ClinicItem::query()->find($state);

                        return $it?->localized_name ?? ('#'.$state);
                    })
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.type'))
                    ->formatStateUsing(fn (string $state): string => $state ? __('clinic_inventory.clinic_stock_movement.types.'.$state) : '')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'restock' => 'success',
                        'consume' => 'danger',
                        'adjustment' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('qty_change_base')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.delta_base'))
                    ->numeric(4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('before_qty_base')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.before'))
                    ->numeric(4)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('after_qty_base')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.after'))
                    ->numeric(4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('performed_by')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.by_user_id'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('related_type')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.related_type'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('related_id')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.related_id'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.notes'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.branch'))
                    ->options(fn () => Branch::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($b) => [$b->id => ($b->localized_name ?? ('#'.$b->id))])
                        ->all()
                    ),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('clinic_inventory.clinic_stock_movement.fields.type'))
                    ->options([
                        'restock' => __('clinic_inventory.clinic_stock_movement.types.restock'),
                        'consume' => __('clinic_inventory.clinic_stock_movement.types.consume'),
                        'adjustment' => __('clinic_inventory.clinic_stock_movement.types.adjustment'),
                    ]),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinicStockMovements::route('/'),
        ];
    }

    /**
     * Make Resource read-only.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
