<?php

namespace App\Filament\Resources\ActivityResource\Pages;

use App\Filament\Resources\ActivityResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Spatie\Activitylog\Models\Activity;

class ViewActivity extends ViewRecord
{
    protected static string $resource = ActivityResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Event')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('When')
                            ->dateTime('Y-m-d H:i:s'),

                        Infolists\Components\TextEntry::make('log_name')
                            ->label('Type')
                            ->badge(),

                        Infolists\Components\TextEntry::make('event')
                            ->badge()
                            ->color(fn (?string $s) => match ($s) {
                                'created' => 'success',
                                'updated' => 'info',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('Subject')
                            ->formatStateUsing(fn ($state, Activity $r) => $r->subject_type
                                ? class_basename($r->subject_type)." #{$state}"
                                : '—'),

                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Actor')
                            ->placeholder('— system'),

                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('—'),
                    ]),

                Infolists\Components\Section::make('Changes')
                    ->schema([
                        Infolists\Components\TextEntry::make('properties_dump')
                            ->label('')
                            ->state(fn (Activity $r) => ActivityResource::formatChanges($r, full: true) ?: '—')
                            ->prose(),
                    ]),

                Infolists\Components\Section::make('Raw properties')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('properties')
                            ->state(fn (Activity $r) => self::flatten($r->properties?->toArray() ?? [])),
                    ]),
            ]);
    }

    protected static function flatten(array $props): array
    {
        $out = [];
        foreach ($props as $bucket => $values) {
            if (is_array($values)) {
                foreach ($values as $k => $v) {
                    $out["{$bucket}.{$k}"] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $out[$bucket] = (string) $values;
            }
        }

        return $out;
    }
}
