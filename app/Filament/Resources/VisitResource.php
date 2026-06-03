<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Models\Branch;
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

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.visit.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.visit.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.visit.label_plural');
    }

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
            Forms\Components\Section::make(__('clinic_visit.sections.visit_context'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('booking_id')
                        ->relationship('booking', 'booking_code')
                        ->label(__('clinic_visit.fields.appointment.label'))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        // FIX: Strict restriction on edit.
                        // Staff cannot change the booking link once created. Only Admin can fix mistakes.
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                        ->helperText(__('clinic_visit.fields.appointment.helper')),

                    Forms\Components\Select::make('restaurant_table_id')
                        ->relationship('room', 'name')
                        ->label(__('clinic_visit.fields.room.label'))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\Select::make('branch_id')
                        ->relationship('branch', 'id', modifyQueryUsing: fn (Builder $query) => $query->forUser(auth()->user()))
                        ->label(__('clinic_visit.fields.branch.label'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->localized_name ?? ('#'.$record->id))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default(fn () => Branch::forUser(auth()->user())->count() === 1 ? Branch::forUser(auth()->user())->first()?->id : null)
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\Select::make('patient_id')
                        ->relationship('patient', 'name')
                        ->label(__('clinic_visit.fields.patient.label'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\Select::make('doctor_id')
                        ->relationship('doctor', 'name')
                        ->label(__('clinic_visit.fields.doctor.label'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\Select::make('status')
                        ->label(__('clinic_visit.fields.status.label'))
                        ->options([
                            'created' => __('clinic_visit.options.status.created'),
                            'awaiting_doctor' => __('clinic_visit.options.status.awaiting_doctor'),
                            'awaiting_stock' => __('clinic_visit.options.status.awaiting_stock'),
                            'in_progress' => __('clinic_visit.options.status.in_progress'),
                            'awaiting_payment' => __('clinic_visit.options.status.awaiting_payment'),
                            'completed' => __('clinic_visit.options.status.completed'),
                            'cancelled' => __('clinic_visit.options.status.cancelled'),
                            'no_show' => __('clinic_visit.options.status.no_show'),
                        ])
                        ->default('created')
                        ->native(false)
                        ->required()
                        // Status needs to be editable by staff to move workflow forward (e.g. In Progress -> Completed)
                        // But we can lock it if it's already completed to prevent tampering
                        ->disabled(fn (?Visit $record) => $record && in_array($record->status, ['completed', 'cancelled']) && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
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
                                            ->title(__('clinic_visit.notifications.service_start_captured'))
                                            ->success()
                                            ->send();
                                    }

                                    // When moving to completed: set completed_at once (do NOT overwrite)
                                    if ($state === 'completed' && ! $record->completed_at) {
                                        $record->update([
                                            'completed_at' => now(),
                                        ]);

                                        Notification::make()
                                            ->title(__('clinic_visit.notifications.completion_time_captured'))
                                            ->success()
                                            ->send();
                                    }
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title(__('clinic_visit.notifications.auto_capture_failed'))
                                        ->body(__('clinic_visit.notifications.check_logs'))
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
                                    ->title(__('clinic_visit.notifications.financial_snapshot_computed'))
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);

                                Notification::make()
                                    ->title(__('clinic_visit.notifications.financial_snapshot_failed'))
                                    ->body(__('clinic_visit.notifications.check_logs'))
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Forms\Components\TextInput::make('source')
                        ->label(__('clinic_visit.fields.source.label'))
                        ->maxLength(255)
                        ->nullable()
                        ->placeholder(__('clinic_visit.fields.source.placeholder'))
                        ->helperText(__('clinic_visit.fields.source.helper'))
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\TextInput::make('booking_code')
                        ->label(__('clinic_visit.fields.booking_code.label'))
                        ->maxLength(255)
                        ->nullable()
                        ->helperText(__('clinic_visit.fields.booking_code.helper'))
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\DateTimePicker::make('checked_in_at')
                        ->label(__('clinic_visit.fields.checked_in_at.label'))
                        ->seconds(false)
                        ->nullable()
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\DateTimePicker::make('queued_at')
                        ->label(__('clinic_visit.fields.queued_at.label'))
                        ->seconds(false)
                        ->nullable()
                        ->disabled(), // Always disabled (system controlled)
                    // ->helperText('Set at check-in.'),

                    Forms\Components\DateTimePicker::make('accepted_at')
                        ->label(__('clinic_visit.fields.accepted_at.label'))
                        ->seconds(false)
                        ->nullable()
                        ->disabled(), // Always disabled (system controlled)
                    // ->helperText('Set when doctor accepts the patient.'),

                    Forms\Components\Select::make('accepted_by_user_id')
                        ->label(__('clinic_visit.fields.accepted_by.label'))
                        ->relationship('acceptedBy', 'name') // see note below
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('service_started_at')
                        ->label(__('clinic_visit.fields.service_started_at.label'))
                        ->seconds(false)
                        ->nullable()
                        ->disabled(),
                    // ->helperText('Set once when the doctor starts the consultation.'),

                    Forms\Components\DateTimePicker::make('completed_at')
                        ->label(__('clinic_visit.fields.completed_at.label'))
                        ->seconds(false)
                        ->nullable()
                        ->disabled(fn (string $operation) => $operation === 'edit' && ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),
                ])
                ->collapsible(),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('markServiceStarted')
                    ->label(__('clinic_visit.actions.mark_service_started'))
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
                                ->title(__('clinic_visit.notifications.not_allowed'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'service_started_at' => now(),
                        ]);

                        Notification::make()
                            ->title(__('clinic_visit.notifications.service_marked_started'))
                            ->success()
                            ->send();
                    }),
            ])->columnSpanFull(),

            Forms\Components\Section::make(__('clinic_visit.sections.follow_up'))
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('follow_up_date')
                        ->label(__('clinic_visit.fields.follow_up_date.label'))
                        ->native(false)
                        ->minDate(now()->startOfDay()) // Restrict to today or future
                        ->nullable(),
                    Forms\Components\Toggle::make('auto_create_follow_up_booking')
                        ->label(__('clinic_visit.fields.auto_create_follow_up_booking.label'))
                        ->helperText(__('clinic_visit.fields.auto_create_follow_up_booking.helper'))
                        ->default(false)
                        ->dehydrated(false),
                    Forms\Components\Actions::make([
                        Action::make('syncFollowUpPlan')
                            ->label(__('clinic_visit.actions.sync_follow_up_plan'))
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
                                        ->title(__('clinic_visit.notifications.follow_up_synced'))
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title(__('clinic_visit.notifications.follow_up_sync_failed'))
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('clinic_visit.fields.notes.label'))
                        ->rows(4)
                        ->columnSpanFull()
                        ->nullable(),
                ])
                ->collapsible(),

            Forms\Components\Section::make(__('clinic_visit.sections.financial_snapshot'))
                ->columns(3)
                ->schema([
                    // Replacement for Section::helperText()
                    Forms\Components\Placeholder::make('financial_helper')
                        ->label('')
                        ->content(__('clinic_visit.fields.financial_helper_content'))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('fees_total')
                        ->label(__('clinic_visit.fields.fees_total.label'))
                        ->helperText(__('clinic_visit.fields.fees_total.helper'))
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('discount_total')
                        ->label(__('clinic_visit.fields.discount_total.label'))
                        ->helperText(__('clinic_visit.fields.discount_total.helper'))
                        ->numeric()
                        ->step('0.001')
                        ->minValue(0)
                        ->default(0)
                        ->nullable()
                        ->disabled(fn () => ! (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),

                    Forms\Components\TextInput::make('items_cost_total')
                        ->label(__('clinic_visit.fields.items_cost_total.label'))
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('items_price_total')
                        ->label(__('clinic_visit.fields.items_price_total.label'))
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('packages_price_total')
                        ->label(__('clinic_visit.fields.packages_price_total.label'))
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('profit_total')
                        ->label(__('clinic_visit.fields.profit_total.label'))
                        ->numeric()
                        ->step('0.001')
                        ->default(0)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('computed_at')
                        ->label(__('clinic_visit.fields.computed_at.label'))
                        ->seconds(false)
                        ->nullable()
                        ->disabled(),

                    Forms\Components\TextInput::make('computed_version')
                        ->label(__('clinic_visit.fields.computed_version.label'))
                        ->maxLength(50)
                        ->default('v1')
                        ->nullable()
                        ->disabled(),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('computeFinancials')
                            ->label(__('clinic_visit.actions.recompute_financials'))
                            ->icon('heroicon-o-calculator')
                            ->visible(fn (?Visit $record) => (bool) $record
                                && static::financialsEnabled()
                                && ($record->status ?? null) === 'completed')
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
                                        ->title(__('clinic_visit.notifications.snapshot_recomputed'))
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title(__('clinic_visit.notifications.snapshot_recompute_failed'))
                                        ->body(__('clinic_visit.notifications.check_logs'))
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
                Tables\Columns\TextColumn::make('id')->label(__('clinic_visit.columns.id'))->sortable(),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label(__('clinic_visit.columns.checked_in'))
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_started_at')
                    ->label(__('clinic_visit.columns.service_started'))
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('patient.name')
                    ->label(__('clinic_visit.columns.patient'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Visit $r) => $r->patient?->phone),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label(__('clinic_visit.columns.doctor'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label(__('clinic_visit.columns.branch'))
                    ->formatStateUsing(fn ($state, Visit $r) => $r->branch?->localized_name ?? ('#'.$state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('room.name')
                    ->label(__('clinic_visit.columns.room'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('queued_at')
                    ->label(__('clinic_visit.columns.queued'))
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label(__('clinic_visit.columns.accepted'))
                    ->dateTime('Y-m-d h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('acceptedBy.name')
                    ->label(__('clinic_visit.columns.accepted_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('booking_code')
                    ->label(__('clinic_visit.columns.code'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('clinic_visit.columns.source'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fees_total')
                    ->label(__('clinic_visit.columns.fees'))
                    ->numeric(3)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('profit_total')
                    ->label(__('clinic_visit.columns.profit'))
                    ->numeric(3)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('computed_at')
                    ->label(__('clinic_visit.columns.computed'))
                    ->dateTime('Y-m-d h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('computed_version')
                    ->label(__('clinic_visit.columns.version'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'gray',
                        'awaiting_doctor' => 'warning',
                        'awaiting_stock' => 'warning',
                        'in_progress' => 'info',
                        'awaiting_payment' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'no_show' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('doctor_id')
                    ->label(__('clinic_visit.filters.doctor'))
                    ->relationship('doctor', 'name'),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('clinic_visit.filters.clinic_branch'))
                    ->multiple()
                    ->options(fn () => Branch::forUser(auth()->user())
                        ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"en\"'))")
                        ->get()
                        ->pluck('localized_name', 'id')
                    )
                    ->default(function () {
                        $availableIds = Branch::forUser(auth()->user())->pluck('id');

                        return $availableIds->count() === 1 ? $availableIds->toArray() : [];
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'created' => __('clinic_visit.options.status.created'),
                        'awaiting_doctor' => __('clinic_visit.options.status.awaiting_doctor'),
                        'awaiting_stock' => __('clinic_visit.options.status.awaiting_stock'),
                        'in_progress' => __('clinic_visit.options.status.in_progress'),
                        'awaiting_payment' => __('clinic_visit.options.status.awaiting_payment'),
                        'completed' => __('clinic_visit.options.status.completed'),
                        'cancelled' => __('clinic_visit.options.status.cancelled'),
                        'no_show' => __('clinic_visit.options.status.no_show'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_accepted')
                    ->label(__('clinic_visit.filters.accepted_question'))
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
                    ->label(__('clinic_visit.actions.open_visit'))
                    ->icon('heroicon-o-folder-open'),

                Tables\Actions\Action::make('recomputeFinancials')
                    ->label(__('clinic_visit.actions.recompute_financials'))
                    ->icon('heroicon-o-calculator')
                    ->visible(fn (Visit $record) => static::financialsEnabled()
                        && ($record->status ?? null) === 'completed')
                    ->requiresConfirmation()
                    ->action(function (Visit $record) {
                        try {
                            app(VisitCostingService::class)->compute($record, (int) (auth()->id() ?? 0));

                            Notification::make()
                                ->title(__('clinic_visit.notifications.snapshot_recomputed'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title(__('clinic_visit.notifications.snapshot_recompute_failed'))
                                ->body(__('clinic_visit.notifications.check_logs'))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                \App\Filament\Exports\ExcelExportActions::bulk(),
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading(__('resources.visit.empty_heading'))
            ->emptyStateDescription(__('resources.visit.empty_description'))
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('id', 'desc');
    }

    protected static function autoTimestampsEnabled(): bool
    {
        return (bool) config('clinic.visit_status_auto_timestamps_enabled', false);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\VisitResource\RelationManagers\VisitChargesRelationManager::class,
            \App\Filament\Resources\VisitResource\RelationManagers\VisitItemsRelationManager::class,
            \App\Filament\Resources\VisitResource\RelationManagers\LabOrdersRelationManager::class,
            \App\Filament\Resources\VisitResource\RelationManagers\VisitPaymentsRelationManager::class,
            \App\Filament\Resources\VisitResource\RelationManagers\VisitPreauthorizationsRelationManager::class,
            \App\Filament\Resources\VisitResource\RelationManagers\FollowUpPlansRelationManager::class,
            \App\Filament\Resources\Concerns\ActivityRelationManager::class,
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
