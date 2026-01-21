<?php

namespace App\Filament\Resources\VisitStockRequestResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Requested Items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('clinicItem.localized_name')
                    ->label('Item')
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty_base')
                    ->label('Qty (Base)')
                    ->numeric(4),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
