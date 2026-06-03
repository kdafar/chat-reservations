<?php

namespace App\Filament\Resources\PatientFileResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AccessLogsRelationManager extends RelationManager
{
    /**
     * PatientFile::accessLogs() — see App\Models\PatientFile.
     */
    protected static string $relationship = 'accessLogs';

    protected static ?string $title = 'Access Log';

    protected static ?string $recordTitleAttribute = 'action';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('accessed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('accessed_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'upload' => 'success',
                        'view' => 'info',
                        'download' => 'primary',
                        'delete' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('accessedBy.name')
                    ->label('User')
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->paginated([25, 50, 100]);
    }
}
