<?php

namespace App\Filament\Resources\Concerns;

use App\Filament\Resources\ActivityResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/**
 * Generic inline audit-log view for any resource whose model uses the
 * [[LogsClinicActivity]] trait. Drop into a Resource's getRelations()
 * and the audit history appears on the edit page filtered to that record.
 *
 * Subclass to override the title; otherwise it just works.
 */
class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity';

    protected static ?string $icon = 'heroicon-o-clock';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $s) => match ($s) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('— system'),

                Tables\Columns\TextColumn::make('changes_preview')
                    ->label('Changes')
                    ->state(fn (Activity $r) => ActivityResource::formatChanges($r))
                    ->wrap()
                    ->limit(160)
                    ->tooltip(fn (Activity $r) => ActivityResource::formatChanges($r, full: true)),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Edits to this record will show up here.');
    }
}
