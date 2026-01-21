<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Models\Visit;
use App\Services\Clinic\FollowUpService;
use App\Services\Clinic\VisitCostingService;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $modelLabel = 'Visit';

    protected static ?string $pluralModelLabel = 'Visits';

    protected static ?int $navigationSort = 20;

    protected static function financialsEnabled(): bool
    {
        return (bool) config('clinic.visit_financials_enabled', false);
    }

    protected static function canOverrideFinancials(): bool
    {
        return (bool) (auth()->user()?->can('clinic_financial_override'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Visit Context')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('booking_id')
                        ->relationship('booking', 'booking_code')
                        ->label('Appointment')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->helperText('Usually created automatically on appointment check-in.'),

                    Forms\Components\Select::make('restaurant_table_id')
                        ->relationship('room', 'name')
                        ->label('Room')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('branch_id')
                        ->relationship('branch', 'id')
                        ->label('Branch')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->localized_name ?? ('#'.$record->id))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('patient_id')
                        ->relationship('patient', 'name')
                        ->label('Patient')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('doctor_id')
                        ->relationship('doctor', 'name')
                        ->label('Doctor')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'created' => 'Created',
                            'in_progress' => 'In Progress',
                            'awaiting_doctor' => 'Awaiting Doctor',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            'no_show' => 'No-show',
                        ])
                        ->default('created')
                        ->native(false)
                        ->required()
                        ->afterStateUpdated(function ($state, ?Visit $record) {
                            if (! $record) {
                                return;
                            }

                            // ---------------------------
                            // Phase A: Auto timestamps (feature-flagged)
                            // ---------------------------
                            if (static::autoTimestampsEnabled()) {
                                try {
                                    // Always work with latest DB state (avoid double-click / multi-tab)
                                    $record->refresh();

                                    // When moving to in_progress: set service_started_at once
                                    if ($state === 'in_progress' && ! $record->service_started_at) {
                                        $record->update([
                                            'service_started_at' => now(),
                                        ]);

                                        Notification::make()
                                            ->title('Service start time captured')
                                            ->success()
                                            ->send();
                                    }

                                    // When moving to completed: set completed_at once (do NOT overwrite)
                                    if ($state === 'completed' && ! $record->completed_at) {
                                        $record->update([
                                            'completed_at' => now(),
                                        ]);

                                        Notification::make()
                                            ->title('Completion time captured')
                                            ->success()
                                            ->send();
                                    }
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title('Failed to auto-capture visit timestamps')
                                        ->body('Please check logs and try again.')
                                        ->danger()
                                        ->send();
                                }
                            }

                            // ---------------------------
                            // Existing: Financial snapshot compute (feature-flagged)
                            // ---------------------------
                            if (! static::financialsEnabled()) {
                                return;
                            }

                            if ($state !== 'completed') {
                                return;
                            }

                            try {
                                // Ensure we compute on freshest snapshot
                                $record->refresh();

                                app(VisitCostingService::class)->compute($record, (int) (auth()->id() ?? 0));

                                Notification::make()
                                    ->title('Financial snapshot computed')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);

                                Notification::make()
                                    ->title('Failed to compute financial snapshot')
                                    ->body('Please check logs and try again.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Forms\Components\TextInput::make('source')
                        ->label('Source')
                        ->maxLength(255)
                        ->nullable()
                        ->placeholder('web / whatsapp / call / walk_in / reception')
                        ->helperText('Attribution only.'),

                    Forms\Components\TextInput::make('booking_code')
                        ->label('Booking Code')
                        ->maxLength(255)
                        ->nullable()
                        ->helperText('Snapshot from appointment (optional).'),

                    Forms\Components\DateTimePicker::make('checked_in_at')
                        ->label('Checked In At')
                        ->seconds(false)
                        ->nullable(),

                    Forms\Components\DateTimePicker::make('queued_at')
                        ->label('Queued At')
                        ->seconds(false)
                        ->nullable()
                        ->disabled()
                        ->helperText('Set at check-in.'),

                    Forms\Components\DateTimePicker::make('accepted_at')
                        ->label('Accepted At')
                        ->seconds(false)
                        ->nullable()
                        ->disabled()
                        ->helperText('Set when doctor accepts the patient.'),

                    Forms\Components\Select::make('accepted_by_user_id')
                        ->label('Accepted By')
                        ->relationship('acceptedBy', 'name') // see note below
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('service_started_at')
                        ->label('Service Started At')
                        ->seconds(false)
                        ->nullable()
                        ->disabled()
                        ->helperText('Set once when the doctor starts the consultation.'),

                    Forms\Components\DateTimePicker::make('completed_at')
                        ->label('Completed At')
                        ->seconds(false)
                        ->nullable(),
                ])
                ->collapsible(),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('markServiceStarted')
                    ->label('Mark Service Started')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (?Visit $record) => $record
                        && ! $record->service_started_at
                    )
                    ->requiresConfirmation()
                    ->action(function (?Visit $record) {
                        if (! $record) {
                            return;
                        }

                        // Hard guard: idempotent
                        if ($record->service_started_at) {
                            return;
                        }

                        // Permission guard (simple + explicit)
                        $user = auth()->user();
                        if (! $user || ! (
                            $user->hasRole('doctor')
                            || $user->hasRole('admin')
                            || $user->hasRole('super_admin')
                        )) {
                            Notification::make()
                                ->title('Not allowed')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'service_started_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Service marked as started')
                            ->success()
                            ->send();
                    }),
            ])->columnSpanFull(),

            Forms\Components\Section::make('Follow-up')
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('follow_up_date')
                        ->label('Follow-up Date')
                        ->native(false)
                        ->minDate(now()->startOfDay()) // Restrict to today or future
                        ->nullable(),
                    Forms\Components\Toggle::make('auto_create_follow_up_booking')
                        ->label('Auto-create follow-up booking')
                        ->helperText('If enabled, system creates a pending booking for the follow-up date/time.')
                        ->default(false)
                        ->dehydrated(false),
                    Forms\Components\Actions::make([
                        Action::make('syncFollowUpPlan')
                            ->label('Sync Follow-up Plan')
                            ->icon('heroicon-o-arrow-path')
                            ->visible(fn (?Visit $record) => (bool) $record && (bool) $record->follow_up_date)
                            ->requiresConfirmation()
                            ->action(function (?Visit $record, array $data) {
                                if (! $record) {
                                    return;
                                }

                                $auto = (bool) ($data['auto_create_follow_up_booking'] ?? false);

                                try {
                                    app(FollowUpService::class)->syncFromVisit(
                                        $record,
                                        $auto,
                                        (int) (auth()->id() ?? 0),
                                    );

                                    Notification::make()
                                        ->title('Follow-up plan synced')
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title('Failed to sync follow-up plan')
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Internal Notes')
                        ->rows(4)
                        ->columnSpanFull()
                        ->nullable(),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Financial Snapshot')
                ->columns(3)
                ->schema([
                    // Replacement for Section::helperText()
                    Forms\Components\Placeholder::make('financial_helper')
                        ->label('')
                        ->content('Computed by VisitCostingService (audit snapshot).')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('fees_total')
                        ->label('Fees Total')
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(fn () => ! static::canOverrideFinancials()),

                    Forms\Components\TextInput::make('discount_total')
                        ->label('Discount Total')
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(fn () => ! static::canOverrideFinancials()),

                    Forms\Components\TextInput::make('items_cost_total')
                        ->label('Items Cost Total')
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('items_price_total')
                        ->label('Items Price Total')
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('profit_total')
                        ->label('Profit Total')
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('computed_at')
                        ->label('Computed At')
                        ->seconds(false)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('computed_version')
                        ->label('Computed Version')
                        ->maxLength(50)
                        ->default('v1')
                        ->nullable()
                        ->disabled(),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('computeFinancials')
                            ->label('Recompute Financials')
                            ->icon('heroicon-o-calculator')
                            ->visible(fn (?Visit $record) => (bool) $record && static::financialsEnabled())
                            ->disabled(fn (?Visit $record) => ! $record || ($record->status ?? null) !== 'completed')
                            ->requiresConfirmation()
                            // FIX: Replaced invalid `Form $form` injection with `\Livewire\Component $livewire`
                            ->action(function (?Visit $record, \Livewire\Component $livewire) {
                                if (! $record) {
                                    return;
                                }

                                try {
                                    app(VisitCostingService::class)->compute($record, (int) (auth()->id() ?? 0));

                                    // STATE SYNC FIX:
                                    // Refresh the record from DB and immediately fill the Form state
                                    // to show new values without a page reload.
                                    if (isset($livewire->form)) {
                                        $livewire->form->fill($record->refresh()->attributesToArray());
                                    }

                                    Notification::make()
                                        ->title('Financial snapshot recomputed')
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title('Failed to recompute snapshot')
                                        ->body('Please check logs and try again.')
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked-in')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_started_at')
                    ->label('Service Started')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Visit $r) => $r->patient?->phone),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch')
                    ->formatStateUsing(fn ($state, Visit $r) => $r->branch?->localized_name ?? ('#'.$state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('room.name')
                    ->label('Room')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('queued_at')
                    ->label('Queued')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Accepted')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('acceptedBy.name')
                    ->label('Accepted By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('booking_code')
                    ->label('Code')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fees_total')
                    ->label('Fees')
                    ->numeric(3)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('profit_total')
                    ->label('Profit')
                    ->numeric(3)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('computed_at')
                    ->label('Computed')
                    ->dateTime('Y-m-d h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('computed_version')
                    ->label('Ver')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'gray',
                        'in_progress' => 'info',
                        'awaiting_doctor' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'no_show' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('doctor_id')
                    ->label('Doctor')
                    ->relationship('doctor', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'created' => 'Created',
                        'in_progress' => 'In Progress',
                        'awaiting_doctor' => 'Awaiting Doctor',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No-show',
                    ]),
                Tables\Filters\TernaryFilter::make('is_accepted')
                    ->label('Accepted?')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('accepted_at'),
                        false: fn (Builder $q) => $q->whereNull('accepted_at'),
                        blank: fn (Builder $q) => $q
                    ),

                Tables\Filters\Filter::make('checked_in_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('checked_in_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('checked_in_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Open Visit')
                    ->icon('heroicon-o-folder-open'),

                Tables\Actions\Action::make('recomputeFinancials')
                    ->label('Recompute Financials')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn () => static::financialsEnabled())
                    ->disabled(fn (Visit $record) => ($record->status ?? null) !== 'completed')
                    ->requiresConfirmation()
                    ->action(function (Visit $record) {
                        try {
                            app(VisitCostingService::class)->compute($record, (int) (auth()->id() ?? 0));

                            Notification::make()
                                ->title('Financial snapshot recomputed')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Failed to recompute snapshot')
                                ->body('Please check logs and try again.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    protected static function autoTimestampsEnabled(): bool
    {
        return (bool) config('clinic.visit_status_auto_timestamps_enabled', false);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\VisitResource\RelationManagers\VisitItemsRelationManager::class,
            \App\Filament\Resources\VisitResource\RelationManagers\VisitPaymentsRelationManager::class,
            \App\Filament\Resources\VisitResource\RelationManagers\FollowUpPlansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisits::route('/'),
            'create' => Pages\CreateVisit::route('/create'),
            'edit' => Pages\EditVisit::route('/{record}/edit'),
        ];
    }
}
