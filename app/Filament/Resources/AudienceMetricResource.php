<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AudienceMetricResource\Pages;
use App\Models\AudienceMetric;
use App\Models\Branch;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AudienceMetricResource extends Resource
{
    protected static ?string $model = AudienceMetric::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $title = 'Audience Metrics';

    protected static ?string $pluralModelLabel = 'Audience Metrics';

    protected static ?string $modelLabel = 'Audience Metric';

    // Optional but fine to keep with correct signature
    public static function getEloquentQuery(): Builder
    {
        return AudienceMetric::query();
    }

    public static function table(Table $table): Table
    {
        return $table
            // COLUMNS
            ->columns([
                Tables\Columns\TextColumn::make('msisdn')->label('Phone')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('bookings_count')->label('Bookings')->badge()->sortable(),
                Tables\Columns\TextColumn::make('confirmed_count')->label('Confirmed')->badge()->color('success')->sortable(),
                Tables\Columns\TextColumn::make('last_booking_at')->label('Last Booking')->dateTime()->sortable(),

                // --- THIS IS THE FIX ---
                // The closure argument was changed from $id to $state to avoid a variable resolution conflict
                // with Filament's internal evaluation scope.
                Tables\Columns\TextColumn::make('last_branch_id')->label('Last Branch')
                    ->formatStateUsing(fn ($state) => $state ? (Branch::find($state)?->name ?? '—') : '—')->toggleable(),
                // --- END OF FIX ---

                Tables\Columns\TextColumn::make('last_party_size')->label('Last Size')->toggleable(),
                Tables\Columns\TextColumn::make('last_wa_in_at')->label('Last WA (in)')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('last_wa_out_at')->label('Last WA (out)')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('session_last_interacted_at')->label('Session Touch')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('last_interaction_at')->label('Last Interaction')->dateTime()->sortable(),
            ])

            // FILTERS (refined & grouped)
            ->filters([
                // Quick segment presets
                Tables\Filters\SelectFilter::make('segment')
                    ->label('Quick segment')
                    ->options([
                        'top10' => 'Top 10 by bookings',
                        'lapsed60' => 'Lapsed ≥ 60 days',
                        'lapsed90' => 'Lapsed ≥ 90 days',
                        'wa7' => 'WA active ≤ 7 days',
                        'wa30' => 'WA active ≤ 30 days',
                    ])
                    ->indicateUsing(fn (array $data) => match ($data['value'] ?? null) {
                        'top10' => 'Top 10 by bookings',
                        'lapsed60' => 'Lapsed ≥ 60 days',
                        'lapsed90' => 'Lapsed ≥ 90 days',
                        'wa7' => 'WA active ≤ 7 days',
                        'wa30' => 'WA active ≤ 30 days',
                        default => null,
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;
                        if ($v === 'top10') {
                            $query->orderByDesc('bookings_count')->limit(10);
                        } elseif ($v === 'lapsed60') {
                            $cut = now()->subDays(60);
                            $query->where(function ($x) use ($cut) {
                                $x->whereNull('last_booking_at')->orWhere('last_booking_at', '<', $cut);
                            });
                        } elseif ($v === 'lapsed90') {
                            $cut = now()->subDays(90);
                            $query->where(function ($x) use ($cut) {
                                $x->whereNull('last_booking_at')->orWhere('last_booking_at', '<', $cut);
                            });
                        } elseif ($v === 'wa7') {
                            $query->where('last_interaction_at', '>=', now()->subDays(7));
                        } elseif ($v === 'wa30') {
                            $query->where('last_interaction_at', '>=', now()->subDays(30));
                        }

                        return $query;
                    }),

                // Booking window
                Tables\Filters\Filter::make('last_booking_between')
                    ->label('Booking window')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->columns(2)
                    ->indicateUsing(fn (array $data) => (! empty($data['from']) || ! empty($data['to'])) ? 'Booking between' : null
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['from'])) {
                            $query->where('last_booking_at', '>=', $data['from'].' 00:00:00');
                        }
                        if (! empty($data['to'])) {
                            $query->where('last_booking_at', '<=', $data['to'].' 23:59:59');
                        }

                        return $query;
                    }),

                // Branch
                Tables\Filters\SelectFilter::make('last_branch_id')
                    ->label('Last Branch')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id')->all()),

                // Source kind
                Tables\Filters\SelectFilter::make('source_kind')
                    ->label('Source kind')
                    ->options([
                        'booked_only' => 'Booked only',
                        'wa_only' => 'WA only (no bookings)',
                        'both' => 'Both WA & bookings',
                    ])
                    ->indicateUsing(fn (array $data) => match ($data['value'] ?? null) {
                        'booked_only' => 'Booked only',
                        'wa_only' => 'WA only',
                        'both' => 'Both',
                        default => null,
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;
                        if ($v === 'booked_only') {
                            $query->where('bookings_count', '>', 0)
                                ->whereNull('last_wa_in_at')
                                ->whereNull('last_wa_out_at')
                                ->whereNull('session_last_interacted_at');
                        } elseif ($v === 'wa_only') {
                            $query->where('bookings_count', '=', 0)
                                ->where(function ($x) {
                                    $x->whereNotNull('last_wa_in_at')
                                        ->orWhereNotNull('last_wa_out_at')
                                        ->orWhereNotNull('session_last_interacted_at');
                                });
                        } elseif ($v === 'both') {
                            $query->where('bookings_count', '>', 0)
                                ->where(function ($x) {
                                    $x->whereNotNull('last_wa_in_at')
                                        ->orWhereNotNull('last_wa_out_at')
                                        ->orWhereNotNull('session_last_interacted_at');
                                });
                        }

                        return $query;
                    }),

                // Min bookings (kept as a simple number)
                Tables\Filters\Filter::make('min_bookings')
                    ->form([
                        Forms\Components\TextInput::make('min')
                            ->label('Min bookings')->numeric()->minValue(0)->placeholder('e.g. 3'),
                    ])
                    ->indicateUsing(fn (array $data) => ! empty($data['min']) ? "Bookings ≥ {$data['min']}" : null)
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['min'])) {
                            $query->where('bookings_count', '>=', (int) $data['min']);
                        }

                        return $query;
                    }),

                // WA recency with friendly options
                Tables\Filters\Filter::make('wa_recent_days')
                    ->label('Any WA Activity (last)')
                    ->form([
                        Forms\Components\Select::make('any_days')
                            ->label('Window recent')
                            ->options([7 => '7 days', 14 => '14 days', 30 => '30 days', 60 => '60 days'])
                            ->native(false),
                    ])
                    ->indicateUsing(fn (array $data) => ! empty($data['any_days']) ? "Any WA ≤ {$data['any_days']}d" : null
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['any_days'])) {
                            $query->where('last_interaction_at', '>=', now()->subDays((int) $data['any_days']));
                        }

                        return $query;
                    }),

                // Inbound only — unique key + chip text
                Tables\Filters\Filter::make('wa_inbound_since')
                    ->label('WA Inbound (user messaged) (last)')
                    ->form([
                        Forms\Components\Select::make('in_days')
                            ->label('Window Inbound')
                            ->options([7 => '7 days', 14 => '14 days', 30 => '30 days'])
                            ->native(false),
                    ])
                    ->indicateUsing(fn (array $data) => ! empty($data['in_days']) ? "Inbound ≤ {$data['in_days']}d" : null
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['in_days'])) {
                            $query->where('last_wa_in_at', '>=', now()->subDays((int) $data['in_days']));
                        }

                        return $query;
                    }),

                // Outbound only — unique key + chip text
                Tables\Filters\Filter::make('wa_outbound_since')
                    ->label('WA Outbound (we messaged) (last)')
                    ->form([
                        Forms\Components\Select::make('out_days')
                            ->label('Window Outbound')
                            ->options([7 => '7 days', 14 => '14 days', 30 => '30 days'])
                            ->native(false),
                    ])
                    ->indicateUsing(fn (array $data) => ! empty($data['out_days']) ? "Outbound ≤ {$data['out_days']}d" : null
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['out_days'])) {
                            $query->where('last_wa_out_at', '>=', now()->subDays((int) $data['out_days']));
                        }

                        return $query;
                    }),
            ])

            // 🔧 UX improvements
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->defaultSort('last_interaction_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudienceMetrics::route('/'),
        ];
    }
}
