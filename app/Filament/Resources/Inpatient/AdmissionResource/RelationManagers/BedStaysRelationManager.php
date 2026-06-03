<?php

namespace App\Filament\Resources\Inpatient\AdmissionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BedStaysRelationManager extends RelationManager
{
    protected static string $relationship = 'bedStays';

    protected static ?string $title = 'Bed history';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('bed.code')->label('Bed')->sortable(),
                Tables\Columns\TextColumn::make('ward.name')->label('Ward')->toggleable(),
                Tables\Columns\TextColumn::make('assigned_at')->dateTime('M j, H:i')->sortable(),
                Tables\Columns\TextColumn::make('released_at')->dateTime('M j, H:i')->placeholder('— current —'),
                Tables\Columns\TextColumn::make('daily_rate')->money(config('app.currency', 'KWD'))->label('Rate'),
                Tables\Columns\TextColumn::make('reason_for_change')->label('Reason')->wrap()->limit(40),
            ])
            ->headerActions([])   // assignment is via the admission action, not here
            ->actions([])
            ->bulkActions([]);
    }
}
