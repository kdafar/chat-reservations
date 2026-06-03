<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Admin-only viewer for the global Spatie activity_log table. Read-only —
 * the log is the source of truth and shouldn't be edited from the UI.
 *
 * Per-resource context is also exposed via the ActivitiesRelationManager
 * on Patient and Visit edit pages so audit history lives next to the data
 * it describes.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'activity-log';

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.platform') ?: 'Platform';
    }

    public static function getNavigationLabel(): string
    {
        return 'Activity Log';
    }

    public static function getModelLabel(): string
    {
        return 'Activity';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Activity Log';
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->hasAnyRole(['admin', 'super_admin']));
    }

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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Type')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (?string $s) => match ($s) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('Subject #')
                    ->formatStateUsing(fn ($state, Activity $r) => $state ? "#{$state}" : '—')
                    ->description(fn (Activity $r) => $r->subject_type ? class_basename($r->subject_type) : null),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('— system')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(80)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('changes_preview')
                    ->label('Changes')
                    ->state(fn (Activity $r) => self::formatChanges($r))
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn (Activity $r) => self::formatChanges($r, full: true)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Type')
                    ->options(fn () => Activity::query()
                        ->select('log_name')
                        ->distinct()
                        ->whereNotNull('log_name')
                        ->orderBy('log_name')
                        ->pluck('log_name', 'log_name')
                        ->toArray()),

                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('created_at', '>=', $d))
                        ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('created_at', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No activity recorded yet')
            ->emptyStateDescription('Every create/update/delete on core models will show up here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }

    public static function formatChanges(Activity $r, bool $full = false): string
    {
        $props = $r->properties?->toArray() ?? [];
        $attrs = $props['attributes'] ?? [];
        $old = $props['old'] ?? [];

        if (empty($attrs)) {
            return '—';
        }

        $parts = [];
        foreach ($attrs as $key => $new) {
            $was = $old[$key] ?? null;
            $newStr = self::stringify($new);
            $wasStr = self::stringify($was);
            $parts[] = $r->event === 'created'
                ? "{$key}: {$newStr}"
                : "{$key}: {$wasStr} → {$newStr}";
        }

        $joined = implode(' · ', $parts);

        return $full ? $joined : (mb_strlen($joined) > 120 ? mb_substr($joined, 0, 117).'…' : $joined);
    }

    protected static function stringify($v): string
    {
        if (is_null($v)) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_array($v) || is_object($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return (string) $v;
    }
}
