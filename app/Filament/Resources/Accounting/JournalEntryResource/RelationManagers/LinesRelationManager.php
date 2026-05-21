<?php

namespace App\Filament\Resources\Accounting\JournalEntryResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Lines';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account.code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->wrap(),
                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit')
                    ->numeric(3)
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? number_format((float) $state, 3) : '—'),
                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit')
                    ->numeric(3)
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? number_format((float) $state, 3) : '—'),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated(false);
    }
}
