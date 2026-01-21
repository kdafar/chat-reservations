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

    protected static ?string $navigationGroup = 'Clinic — Inventory';

    protected static ?string $modelLabel = 'Stock Movement';

    protected static ?string $pluralModelLabel = 'Stock Movements';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch')
                    ->formatStateUsing(function ($state) {
                        $b = Branch::query()->find($state);

                        return $b?->localized_name ?? ('#'.$state);
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clinic_item_id')
                    ->label('Item')
                    ->formatStateUsing(function ($state) {
                        $it = ClinicItem::query()->find($state);

                        return $it?->localized_name ?? ('#'.$state);
                    })
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'restock' => 'success',
                        'consume' => 'danger',
                        'adjustment' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('qty_change_base')
                    ->label('Delta (Base)')
                    ->numeric(4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('before_qty_base')
                    ->label('Before')
                    ->numeric(4)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('after_qty_base')
                    ->label('After')
                    ->numeric(4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('performed_by')
                    ->label('By (User ID)')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('related_type')
                    ->label('Related Type')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('related_id')
                    ->label('Related ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->label('Type')
                    ->options([
                        'restock' => 'Restock',
                        'consume' => 'Consume',
                        'adjustment' => 'Adjustment',
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
