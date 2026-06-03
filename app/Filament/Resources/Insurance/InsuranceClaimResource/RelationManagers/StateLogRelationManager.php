<?php

namespace App\Filament\Resources\Insurance\InsuranceClaimResource\RelationManagers;

use App\Filament\Resources\Insurance\InsuranceClaimResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StateLogRelationManager extends RelationManager
{
    protected static string $relationship = 'stateLogs';

    protected static ?string $title = 'State History';

    protected static ?string $recordTitleAttribute = 'to_status';

    public function table(Table $table): Table
    {
        return $table
            // Newest first so users see the latest action at the top.
            ->defaultSort('changed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_status')
                    ->label('From')
                    ->badge()
                    ->color(fn (?string $state) => $state ? InsuranceClaimResource::statusColor($state) : 'gray')
                    ->formatStateUsing(fn (?string $state) => $state
                        ? (InsuranceClaimResource::statusOptions()[$state] ?? $state)
                        : '—'),

                Tables\Columns\TextColumn::make('to_status')
                    ->label('To')
                    ->badge()
                    ->color(fn (string $state) => InsuranceClaimResource::statusColor($state))
                    ->formatStateUsing(fn (string $state) => InsuranceClaimResource::statusOptions()[$state] ?? $state),

                Tables\Columns\TextColumn::make('changedBy.name')
                    ->label('Changed By')
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('notes')
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->actions([])
            ->paginated(false);
    }
}
