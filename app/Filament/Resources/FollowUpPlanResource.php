<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FollowUpPlanResource\Pages;
use App\Models\Booking;
use App\Models\FollowUpPlan;
use App\Models\Patient;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class FollowUpPlanResource extends Resource
{
    protected static ?string $model = FollowUpPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Clinic — Compliance';

    protected static ?string $modelLabel = 'Follow-up Plan';

    protected static ?string $pluralModelLabel = 'Follow-up Plans';

    protected static ?int $navigationSort = 20;

    protected static function hasCol(string $col): bool
    {
        static $cache = [];
        $key = static::$model.':'.$col;

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasColumn((new FollowUpPlan)->getTable(), $col);
        }

        return (bool) $cache[$key];
    }

    public static function form(Form $form): Form
    {
        // Read-focused (created by Visit save hook)
        return $form->schema([
            Forms\Components\Section::make('Follow-up Plan')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('id')
                        ->label('#')
                        ->disabled(),

                    Forms\Components\Select::make('patient_id')
                        ->label('Patient')
                        ->relationship('patient', 'name')
                        ->searchable()
                        ->preload()
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('suggested_at')
                        ->label('Suggested At')
                        ->seconds(false)
                        ->disabled(),

                    Forms\Components\Toggle::make('auto_create_booking')
                        ->label('Auto-create Booking')
                        ->disabled(),

                    Forms\Components\TextInput::make('source_visit_id')
                        ->label('Source Visit ID')
                        ->disabled(),

                    Forms\Components\TextInput::make('booking_id')
                        ->label('Created Booking ID')
                        ->disabled()
                        ->visible(fn () => static::hasCol('booking_id')),

                    Forms\Components\TextInput::make('branch_id')
                        ->label('Branch ID')
                        ->disabled()
                        ->visible(fn () => static::hasCol('branch_id')),

                    Forms\Components\DateTimePicker::make('created_at')
                        ->label('Created At')
                        ->seconds(false)
                        ->disabled()
                        ->visible(fn () => static::hasCol('created_at')),

                    Forms\Components\DateTimePicker::make('updated_at')
                        ->label('Updated At')
                        ->seconds(false)
                        ->disabled()
                        ->visible(fn () => static::hasCol('updated_at')),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('suggested_at')
                    ->label('Suggested')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable()
                    ->description(fn (FollowUpPlan $r) => $r->patient?->phone),

                Tables\Columns\IconColumn::make('auto_create_booking')
                    ->label('Auto Booking')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_visit_id')
                    ->label('Visit')
                    ->formatStateUsing(function ($state) {
                        $v = Visit::query()->find($state);

                        return $v?->booking_code ?? ('Visit #'.$state);
                    })
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('booking_id')
                    ->label('Booking')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }
                        $b = Booking::query()->find($state);

                        return $b?->booking_code ?? ('Booking #'.$state);
                    })
                    ->visible(fn () => static::hasCol('booking_id'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch')
                    ->visible(fn () => static::hasCol('branch_id'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d h:i A')
                    ->visible(fn () => static::hasCol('created_at'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('patient_id')
                    ->label('Patient')
                    ->options(fn () => Patient::query()
                        ->orderBy('id', 'desc')
                        ->limit(500)
                        ->get(['id', 'name'])
                        ->mapWithKeys(fn ($p) => [$p->id => ($p->name ?? ('#'.$p->id))])
                        ->all()
                    )
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('has_booking')
                    ->label('Has Booking?')
                    ->queries(
                        true: fn (Builder $q) => static::hasCol('booking_id') ? $q->whereNotNull('booking_id') : $q,
                        false: fn (Builder $q) => static::hasCol('booking_id') ? $q->whereNull('booking_id') : $q,
                        blank: fn (Builder $q) => $q,
                    )
                    ->visible(fn () => static::hasCol('booking_id')),

                Tables\Filters\Filter::make('suggested_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('suggested_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('suggested_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function canCreate(): bool
    {
        // Plans are generated from Visit save hook. Keep read-only to avoid garbage.
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFollowUpPlans::route('/'),
            'view' => Pages\ViewFollowUpPlan::route('/{record}'),
        ];
    }
}
