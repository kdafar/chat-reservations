<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\RestaurantTable;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Clinic\VisitCostingService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $slug = 'bookings';

    protected static ?int $navigationSort = 10;

    // Status Constants aligned with DB Enum
    const STATUS_PENDING = 'pending';

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_COMPLETED = 'completed';

    public static function getNavigationLabel(): string
    {
        return 'Appointments';
    }

    public static function getModelLabel(): string
    {
        return 'Appointment';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Appointments';
    }

    public static function getNavigationBadge(): ?string
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        return (string) static::getModel()::query()
            ->whereDate('res_date', Carbon::now($tz)->toDateString())
            ->where('status', self::STATUS_CONFIRMED)
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $hasPendingToday = static::getModel()::query()
            ->whereDate('res_date', Carbon::now($tz)->toDateString())
            ->whereIn('status', [self::STATUS_PENDING])
            ->exists();

        return $hasPendingToday ? 'warning' : 'gray';
    }

    /**
     * ARCHITECTURE FIX: Move eager loading here.
     * Prevents Builder conflicts in modifyQueryUsing during search operations.
     * UPDATE: Added 'patient' and 'contact' to prevent Lazy Loading errors in Views.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['branch', 'doctor', 'table', 'patient', 'contact']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Clinic & Provider')
                ->description('Select the facility and the doctor.')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label('Clinic')
                        // Optimization: Use pluck directly on collection
                        ->options(fn () => Partner::forUser(auth()->user())
                            ->get()
                            ->pluck('name_label', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        // FIX: Hydrate partner_id on Edit safely
                        ->afterStateHydrated(function (Forms\Components\Select $component, ?Booking $record) {
                            if ($component->getState()) {
                                return;
                            }

                            if ($record && $record->branch_id) {
                                $branch = $record->relationLoaded('branch')
                                    ? $record->branch
                                    : Branch::find($record->branch_id);

                                if ($branch) {
                                    $component->state($branch->partner_id);
                                }
                            }
                        })
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('branch_id', null);
                            $set('doctor_id', null);
                            $set('res_time', null);
                            $set('table_id', null);
                        })
                        ->dehydrated(false)
                        ->default(fn () => Partner::forUser(auth()->user())->first()?->id)
                        ->columnSpan(1),

                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->options(function (Forms\Get $get) {
                            $partnerId = $get('partner_id');
                            if (! $partnerId) {
                                return [];
                            }

                            // Optimization: Use pluck for cleaner collection handling
                            return Branch::where('partner_id', $partnerId)
                                ->get()
                                ->pluck('localized_name', 'id');
                        })
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('doctor_id', null);
                            $set('res_time', null);
                            // Clear room if branch changes
                            $set('table_id', null);
                        })
                        ->default(fn (Forms\Get $get) => Branch::where('partner_id', $get('partner_id'))->first()?->id)
                        ->columnSpan(1),

                    Forms\Components\Select::make('doctor_id')
                        ->label('Doctor')
                        ->options(function (Forms\Get $get) {
                            $branchId = $get('branch_id');
                            if (! $branchId) {
                                return [];
                            }

                            return Doctor::where('branch_id', $branchId)
                                ->where('is_active', true)
                                ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                            $set('res_time', null);

                            // Handle "doctor cleared"
                            if (! $state) {
                                // Release auto-room and allow selection
                                $set('table_id', null);

                                return;
                            }

                            // Safety Check - Don't override if table already set manually
                            if ($get('table_id')) {
                                return;
                            }

                            // Auto-assign room using 'restaurant_table_id' from doctors table
                            $doctor = Doctor::find($state);
                            if ($doctor && $doctor->restaurant_table_id) {
                                $set('table_id', $doctor->restaurant_table_id);
                            }
                        })
                        ->required()
                        ->columnSpan(1),
                ]),

            Forms\Components\Section::make('Schedule')
                ->description('Date, time, and room assignment.')
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('res_date')
                        ->label('Date')
                        ->native(false)
                        ->required()
                        ->live()
                        ->minDate(function ($context, $record) {
                            if ($context === 'edit' && $record) {
                                return null;
                            }

                            return now()->startOfDay();
                        })
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('res_time', null))
                        ->rule(function (Forms\Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                $branchId = (int) $get('branch_id');
                                $doctorId = (int) $get('doctor_id');

                                if (! $branchId) {
                                    return;
                                }

                                $service = app(AvailabilityService::class);
                                $docParam = $doctorId > 0 ? $doctorId : null;

                                $hasSlots = count($service->timesFor($branchId, $value, 1, $docParam)) > 0;

                                if (! $hasSlots) {
                                    $fail('No availability on this date for the selected branch/doctor.');
                                }
                            };
                        })
                        ->columnSpan(1),

                    Forms\Components\Select::make('res_time')
                        ->label('Time')
                        ->options(function (Forms\Get $get) {
                            $branchId = (int) $get('branch_id');
                            $date = $get('res_date');
                            $doctorId = (int) ($get('doctor_id') ?? 0);

                            if (! $branchId || ! $date) {
                                return [];
                            }

                            $service = app(AvailabilityService::class);
                            $slots = $service->timesFor($branchId, $date, 1, $doctorId ?: null);

                            return collect($slots)->mapWithKeys(fn ($slot) => [
                                $slot['value'] => $slot['label'],
                            ]);
                        })
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(1),

                    Forms\Components\Select::make('table_id')
                        ->label('Room / Table')
                        ->options(function (Forms\Get $get) {
                            $branchId = (int) $get('branch_id');
                            if (! $branchId) {
                                return [];
                            }

                            return RestaurantTable::where('branch_id', $branchId)->pluck('name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        // Smart Disable - Only disable if config flag is TRUE and doctor has a room.
                        ->disabled(function (string $context, Forms\Get $get) {
                            if ($context !== 'create') {
                                return false;
                            }

                            if (! config('clinic.lock_doctor_room', false)) {
                                return false;
                            }

                            $doctorId = $get('doctor_id');
                            if (! $doctorId) {
                                return false;
                            }

                            $doctor = Doctor::find($doctorId);

                            return (bool) ($doctor?->restaurant_table_id);
                        })
                        ->helperText(function (string $context, Forms\Get $get) {
                            if ($context !== 'create') {
                                return null;
                            }

                            $doctorId = $get('doctor_id');
                            if (! $doctorId) {
                                return 'Select doctor to auto-assign room, or choose manually.';
                            }

                            $doctor = Doctor::find($doctorId);
                            if (! $doctor?->restaurant_table_id) {
                                return 'Select a room manually.';
                            }

                            return config('clinic.lock_doctor_room', false)
                                ? 'Auto-assigned from doctor. Change doctor to change room.'
                                : 'Auto-assigned from doctor. You can override this if needed.';
                        })
                        ->dehydrated()
                        ->columnSpan(1),

                    Forms\Components\Hidden::make('res_start')
                        ->dehydrateStateUsing(function (callable $get) {
                            $date = $get('res_date');
                            $time = (string) ($get('res_time') ?? '');

                            if (! $date || $time === '') {
                                return null;
                            }

                            $dateStr = ($date instanceof \DateTimeInterface)
                                ? $date->format('Y-m-d')
                                : substr((string) $date, 0, 10);

                            $time = trim($time);
                            if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                                $time .= ':00';
                            }

                            $tz = config('app.timezone', 'Asia/Kuwait');

                            return Carbon::parse("{$dateStr} {$time}", $tz)->seconds(0);
                        })
                        ->dehydrated(false),

                    Forms\Components\Hidden::make('res_end')
                        ->dehydrateStateUsing(function (callable $get) {
                            return self::calculateSlotEnd(
                                $get('res_date'),
                                $get('res_time'),
                                $get('branch_id')
                            );
                        })
                        ->dehydrated(false),
                ]),

            Forms\Components\Section::make('Patient Details')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('contact_id')
                        ->relationship('contact', 'name')
                        ->label('WhatsApp Contact')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            // 1. Null Defense
                            if (! $state) {
                                return;
                            }

                            $contact = \App\Models\WhatsappContact::find($state);
                            if (! $contact) {
                                return;
                            }

                            // 2. Normalize MSISDN (The "Bridge")
                            $raw = (string) $contact->msisdn;
                            $digitsOnly = preg_replace('/\D/', '', $raw);
                            $finalPhone = $digitsOnly ?: $raw;

                            $set('msisdn', $finalPhone);

                            // 3. Auto-Fill Patient (Safe Lookup)
                            // If patient is already manually selected, do not overwrite to prevent frustration.
                            if ($get('patient_id')) {
                                return;
                            }

                            $partnerId = $get('partner_id');

                            // Search for patient by phone
                            $patient = \App\Models\Patient::query()
                                ->when($partnerId && Schema::hasColumn('patients', 'partner_id'), fn ($q) => $q->where('partner_id', $partnerId))
                                ->where(function ($q) use ($finalPhone, $digitsOnly) {
                                    $q->where('phone', $finalPhone);
                                    if ($digitsOnly) {
                                        $q->orWhere('phone', 'LIKE', "%{$digitsOnly}");
                                    }
                                })
                                ->first();

                            if ($patient) {
                                $set('patient_id', $patient->id);
                            }
                        })
                        ->columnSpan(1),

                    Forms\Components\Select::make('patient_id')
                        ->label('Patient')
                        // Async search instead of loading all rows
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search, Forms\Get $get) {
                            $query = Patient::query();

                            $partnerId = $get('partner_id');
                            if ($partnerId && Schema::hasColumn('patients', 'partner_id')) {
                                $query->where('partner_id', $partnerId);
                            }

                            return $query->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            })
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($p) => [$p->id => "{$p->name} ({$p->phone})"])
                                ->toArray();
                        })
                        ->getOptionLabelUsing(function ($value) {
                            $p = $value ? Patient::find($value) : null;

                            return $p ? "{$p->name} ({$p->phone})" : '—';
                        })
                        ->live()
                        // Safe Create Option Form
                        ->createOptionForm(array_merge(
                            [
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->label('Full Name'),
                                Forms\Components\TextInput::make('phone')
                                    ->required()
                                    ->unique('patients', 'phone')
                                    ->label('Phone Number'),
                            ],
                            Schema::hasColumn('patients', 'partner_id') ? [
                                Forms\Components\Select::make('partner_id')
                                    ->label('Clinic')
                                    ->options(fn () => Partner::get()->pluck('name_label', 'id'))
                                    ->default(fn () => Partner::first()?->id)
                                    ->required(),
                            ] : []
                        ))
                        ->createOptionUsing(fn (array $data) => Patient::create($data)->id)
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            // 1. Null Defense
                            if (! $state) {
                                return;
                            }

                            $patient = Patient::find($state);
                            if (! $patient) {
                                return;
                            }

                            // 2. Normalize Phone (The "Bridge")
                            $raw = (string) $patient->phone;
                            $digitsOnly = preg_replace('/\D/', '', $raw);
                            $finalPhone = $digitsOnly ?: $raw;

                            $set('msisdn', $finalPhone);

                            // 3. Auto-Fill WhatsApp Contact (Safe Lookup)
                            // If contact is already selected, do not overwrite.
                            if ($get('contact_id')) {
                                return;
                            }

                            $contact = \App\Models\WhatsappContact::query()
                                ->where('msisdn', $finalPhone)
                                ->when($digitsOnly, fn ($q) => $q->orWhere('msisdn', 'LIKE', "%{$digitsOnly}"))
                                ->first();

                            if ($contact) {
                                $set('contact_id', $contact->id);
                            }
                        })
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('msisdn')
                        ->label('Phone Number')
                        ->tel()
                        ->required()
                        ->maxLength(32)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                            if (! $state) {
                                return;
                            }

                            $partnerId = $get('partner_id');

                            $sanitized = trim($state);
                            $digitsOnly = preg_replace('/\D/', '', $sanitized);

                            if ($digitsOnly && $digitsOnly !== $state) {
                                $set('msisdn', $digitsOnly);
                            }

                            $baseQuery = Patient::query();
                            if ($partnerId && Schema::hasColumn('patients', 'partner_id')) {
                                $baseQuery->where('partner_id', $partnerId);
                            }

                            // 1. Exact Match
                            $patient = (clone $baseQuery)->where('phone', $digitsOnly ?: $sanitized)->first();

                            // 2. Fuzzy Fallback
                            if (! $patient && $digitsOnly) {
                                $patient = (clone $baseQuery)->where('phone', 'LIKE', "%{$digitsOnly}")->first();
                            }

                            if ($patient) {
                                $set('patient_id', $patient->id);
                            } else {
                                $contact = \App\Models\WhatsappContact::where('msisdn', $sanitized)
                                    ->orWhere('msisdn', 'LIKE', "%{$digitsOnly}")
                                    ->first();
                                if ($contact) {
                                    $set('contact_id', $contact->id);
                                }
                            }
                        })
                        ->columnSpan(1),

                    Forms\Components\Select::make('source')
                        ->label('Source')
                        ->options([
                            'web' => 'Website',
                            'whatsapp' => 'WhatsApp',
                            'call' => 'Phone Call',
                            'walk_in' => 'Walk-in',
                            'reception' => 'Reception Desk',
                        ])
                        ->default('reception')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            self::STATUS_PENDING => 'Pending / Hold',
                            self::STATUS_CONFIRMED => 'Confirmed',
                            self::STATUS_CANCELLED => 'Cancelled',
                            self::STATUS_COMPLETED => 'Completed',
                        ])
                        ->required()
                        ->default(self::STATUS_CONFIRMED)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('booking_code')
                        ->label('Booking Code')
                        ->maxLength(16)
                        ->placeholder('Auto-generated')
                        ->dehydrateStateUsing(function ($state, string $context) {
                            if ($context === 'edit') {
                                return $state;
                            }

                            return filled($state) ? $state : strtoupper(Str::random(6));
                        })
                        ->columnSpan(1),

                    Forms\Components\Hidden::make('party_size')->default(1),
                ]),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')->label('Medical Notes')->rows(3),
                ]),

            /**
             * ADDITION: Patient History Section
             * Safe implementation: Read-only placeholder that reacts to 'patient_id'.
             * This does not affect form submission data.
             */
            Forms\Components\Section::make('Patient History')
                ->description('Previous visits, items, and payments.')
                ->collapsible()
                ->collapsed(false)
                ->schema([
                    Forms\Components\Placeholder::make('visits_list')
                        ->label('')
                        ->content(function (Forms\Get $get) {
                            $patientId = $get('patient_id');
                            if (! $patientId) {
                                return new HtmlString('<p class="text-sm text-gray-400 italic">Select a patient to view history.</p>');
                            }

                            // Fetch last 5 visits safely with expanded relations
                            // UPDATED: Eager loading visitItems, payments, followUpPlans
                            $visits = \App\Models\Visit::query()
                                ->with([
                                    'doctor',
                                    'branch',
                                    'visitItems.clinicItem', // Load items/services
                                    'payments',             // Load payments
                                    'followUpPlans',         // Load follow-ups
                                ])
                                ->where('patient_id', $patientId)
                                ->latest('service_started_at')
                                ->limit(5)
                                ->get();

                            return new HtmlString(
                                view('filament.resources.bookings.components.patient-history', ['visits' => $visits])->render()
                            );
                        }),
                ])
                ->hidden(fn (Forms\Get $get) => ! $get('patient_id')),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        return $table
            ->striped()
            ->poll('10s')
            // ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->defaultSort('res_date', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('res_date')
                    ->label('Date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->description(function (Booking $r) {
                        // DEFENSIVE CODE: Handle nulls
                        if (! $r->res_date) {
                            return null;
                        }

                        $tz = config('app.timezone', 'Asia/Kuwait');

                        // 1. Try to use the computed timestamp column first (most accurate)
                        $target = $r->res_start;

                        // 2. Legacy Fallback: If res_start is null, combine date + time manually
                        if (! $target) {
                            $timeStr = $r->res_time ? (string) $r->res_time : '00:00:00';
                            $target = Carbon::parse($r->res_date->format('Y-m-d').' '.$timeStr, $tz);
                        } else {
                            // Ensure timezone consistency
                            $target = $target->timezone($tz);
                        }

                        return $target->isPast()
                            ? 'Was scheduled '.$target->diffForHumans() // Now compares against 13:45
                            : 'Scheduled '.$target->diffForHumans();
                    }),

                Tables\Columns\TextColumn::make('res_time')
                    ->label('Time')
                    ->formatStateUsing(function ($state) {
                        return $state ? substr((string) $state, 0, 5) : '—';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Clinic Branch')
                    ->formatStateUsing(fn ($state, $record) => $record->branch?->localized_name)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('party_size')
                    ->label('Pax')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // FIXED: Patient Display Column using whereHas to prevent Builder corruption
                Tables\Columns\TextColumn::make('patient_display')
                    ->label('Patient Name')
                    ->getStateUsing(fn (Booking $r) => $r->patient?->name ?? $r->contact?->name ?? '—')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where(function (Builder $sub) use ($search) {
                            $sub->whereHas('patient', fn (Builder $p) => $p->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('contact', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
                        });
                    }),

                Tables\Columns\TextColumn::make('msisdn')->label('Phone')->searchable(),

                Tables\Columns\TextColumn::make('booking_code')
                    ->label('Code')
                    ->badge()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyMessage('Appointment code copied'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => [
                        self::STATUS_CONFIRMED => 'success',
                        self::STATUS_PENDING => 'warning',
                        self::STATUS_COMPLETED => 'primary',
                        self::STATUS_CANCELLED => 'danger',
                    ][$state] ?? 'gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('qr_token')
                    ->label('QR Pass')
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('heroicon-o-x-mark')
                    ->tooltip(fn (Booking $r) => $r->qr_token ? 'QR pass ready' : 'No QR pass yet')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('checked_in_at')
                    ->label('Checked in?')
                    ->boolean()
                    ->trueIcon('heroicon-o-user')
                    ->falseIcon('heroicon-o-user')
                    ->getStateUsing(fn ($record) => ! is_null($record->checked_in_at))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('consultation_paid')
                    ->label('Consultation')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->getStateUsing(fn (Booking $r) => self::isConsultationPaidForBooking($r))
                    ->tooltip(fn (Booking $r) => self::isConsultationPaidForBooking($r)
                        ? 'Consultation paid'
                        : 'Consultation not paid'
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label('Created')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Clinic Branch')
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
                    ->multiple()
                    ->options([
                        self::STATUS_PENDING => 'Pending / Hold',
                        self::STATUS_CONFIRMED => 'Confirmed',
                        self::STATUS_COMPLETED => 'Completed',
                        self::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->default([self::STATUS_CONFIRMED]),

                Tables\Filters\Filter::make('date_range')
                    ->label('Date range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('res_start', '>=', $from))
                        ->when($data['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('res_start', '<=', $to))
                    ),

                Tables\Filters\SelectFilter::make('when')
                    ->label('Date quick picks')
                    ->options([
                        'today' => 'Today',
                        'tomorrow' => 'Tomorrow',
                        'week' => 'This week',
                        'past' => 'Past',
                    ])
                    ->native(false)
                    ->query(function (Builder $q, array $data) use ($tz) {

                        $preset = $data['value'] ?? null;
                        if (! $preset) {
                            return $q;
                        }

                        $now = Carbon::now($tz);

                        return match ($preset) {
                            'today' => $q->whereDate('res_date', $now->toDateString()),
                            'tomorrow' => $q->whereDate('res_date', $now->copy()->addDay()->toDateString()),
                            'week' => $q->whereBetween('res_date', [
                                $now->copy()->startOfWeek()->toDateString(),
                                $now->copy()->endOfWeek()->toDateString(),
                            ]),
                            'past' => $q->where('res_date', '<', $now->toDateString()),
                            default => $q,
                        };
                    })->default('today'),

                Tables\Filters\SelectFilter::make('time_of_day')
                    ->label('Time of day')
                    ->options([
                        'morning' => 'Morning (08–12)',
                        'afternoon' => 'Afternoon (12–17)',
                        'evening' => 'Evening (17–23)',
                    ])
                    ->native(false)
                    ->query(function (Builder $q, array $data) {

                        $slot = $data['value'] ?? null;
                        if (! $slot) {
                            return $q;
                        }

                        return match ($slot) {
                            'morning' => $q->whereRaw('res_time >= ? AND res_time < ?', ['08:00:00', '12:00:00']),
                            'afternoon' => $q->whereRaw('res_time >= ? AND res_time < ?', ['12:00:00', '17:00:00']),
                            'evening' => $q->whereRaw('res_time >= ? AND res_time <= ?', ['17:00:00', '23:00:00']),
                            default => $q,
                        };
                    }),

                Tables\Filters\Filter::make('party_range')
                    ->label('Patients count')
                    ->form([
                        Forms\Components\TextInput::make('min')->numeric()->label('Min'),
                        Forms\Components\TextInput::make('max')->numeric()->label('Max'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        $min = $data['min'] ?? null;
                        $max = $data['max'] ?? null;
                        if ($min !== null && $min !== '') {
                            $q->where('party_size', '>=', (int) $min);
                        }
                        if ($max !== null && $max !== '') {
                            $q->where('party_size', '<=', (int) $max);
                        }

                        return $q;
                    }),

                Tables\Filters\TernaryFilter::make('checked_in')
                    ->label('Check-in')
                    ->trueLabel('Checked in')
                    ->falseLabel('Not checked in')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('checked_in_at'),
                        false: fn (Builder $q) => $q->whereNull('checked_in_at'),
                        blank: fn (Builder $q) => $q
                    ),

                Tables\Filters\TernaryFilter::make('has_qr')
                    ->label('QR Pass')
                    ->trueLabel('With QR')
                    ->falseLabel('Without QR')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('qr_token'),
                        false: fn (Builder $q) => $q->whereNull('qr_token'),
                        blank: fn (Builder $q) => $q
                    ),

                Tables\Filters\Filter::make('no_show')
                    ->label('No-show (auto)')
                    ->query(fn (Builder $q) => $q->where('status', self::STATUS_CONFIRMED)
                        ->whereNull('checked_in_at')
                        ->whereNotNull('res_end')
                        ->where('res_end', '<', Carbon::now(config('app.timezone', 'Asia/Kuwait')))
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('View'),

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->visible(fn (Booking $r) => is_null($r->checked_in_at) && ! self::isTerminal($r)),

                ActionGroup::make([
                    Tables\Actions\Action::make('open_whatsapp')
                        ->label('WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->url(fn (Booking $r) => 'https://wa.me/'.preg_replace('/\D+/', '', (string) $r->msisdn))
                        ->openUrlInNewTab()
                        ->visible(fn (Booking $r) => filled($r->msisdn)),

                    Tables\Actions\Action::make('reschedule')
                        ->label('Reschedule')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->form([
                            Forms\Components\DatePicker::make('new_date')
                                ->required()
                                ->label('New date')
                                ->native(false)
                                ->live()
                                ->minDate(now()->startOfDay())
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('new_time', null))
                                ->rule(function (Booking $r) {
                                    return function (string $attribute, $value, \Closure $fail) use ($r) {
                                        $service = app(AvailabilityService::class);
                                        $hasSlots = count($service->timesFor(
                                            $r->branch_id,
                                            $value,
                                            max(1, $r->party_size ?? 1),
                                            $r->doctor_id
                                        )) > 0;

                                        if (! $hasSlots) {
                                            $fail('No availability on this date.');
                                        }
                                    };
                                }),

                            Forms\Components\Select::make('new_time')
                                ->required()
                                ->label('New time')
                                ->options(function (Forms\Get $get, Booking $r) {
                                    $tz = config('app.timezone', 'Asia/Kuwait');

                                    $date = $get('new_date');
                                    if (! $date) {
                                        return [];
                                    }

                                    // Normalize DatePicker value to Y-m-d
                                    $dateStr = ($date instanceof \DateTimeInterface)
                                        ? $date->format('Y-m-d')
                                        : substr((string) $date, 0, 10);

                                    if ($dateStr === '') {
                                        return [];
                                    }

                                    // Prevent past dates from even showing time options
                                    try {
                                        $picked = Carbon::parse($dateStr, $tz)->startOfDay();
                                    } catch (\Throwable) {
                                        return [];
                                    }

                                    $today = Carbon::now($tz)->startOfDay();
                                    if ($picked->lt($today)) {
                                        return [];
                                    }

                                    // Doctor must be valid for the branch (avoid doctor moved/inactive)
                                    $doctorId = $r->doctor_id ? (int) $r->doctor_id : null;

                                    if ($doctorId) {
                                        $doctorOk = Doctor::query()
                                            ->whereKey($doctorId)
                                            ->where('branch_id', (int) $r->branch_id)
                                            ->where('is_active', true)
                                            ->exists();

                                        if (! $doctorOk) {
                                            return [];
                                        }
                                    }

                                    $service = app(AvailabilityService::class);

                                    $slots = $service->timesFor(
                                        (int) $r->branch_id,
                                        $dateStr,
                                        max(1, (int) ($r->party_size ?? 1)),
                                        $doctorId // null allowed
                                    );

                                    return collect($slots)->mapWithKeys(fn ($s) => [$s['value'] => $s['label']]);
                                })
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (array $data, Booking $r) {
                            if ($r->checked_in_at) {
                                Notification::make()->title('Cannot reschedule checked-in appointment')->danger()->send();

                                return;
                            }

                            $tz = config('app.timezone', 'Asia/Kuwait');

                            // Normalize new_date
                            $dateStr = ($data['new_date'] instanceof \DateTimeInterface)
                                ? $data['new_date']->format('Y-m-d')
                                : substr((string) $data['new_date'], 0, 10);

                            if ($dateStr === '') {
                                Notification::make()->title('Invalid date')->danger()->send();

                                return;
                            }

                            // Hard guard: no past date
                            try {
                                $picked = Carbon::parse($dateStr, $tz)->startOfDay();
                            } catch (\Throwable) {
                                Notification::make()->title('Invalid date')->danger()->send();

                                return;
                            }

                            $today = Carbon::now($tz)->startOfDay();
                            if ($picked->lt($today)) {
                                Notification::make()->title('Cannot reschedule to a past date')->danger()->send();

                                return;
                            }

                            // Normalize new_time to HH:MM:SS
                            $timeStr = trim((string) ($data['new_time'] ?? ''));
                            if ($timeStr === '') {
                                Notification::make()->title('Invalid time')->danger()->send();

                                return;
                            }
                            if (preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
                                $timeStr .= ':00';
                            }

                            // Doctor must still be valid for this branch
                            $doctorId = $r->doctor_id ? (int) $r->doctor_id : null;

                            if ($doctorId) {
                                $doctorOk = Doctor::query()
                                    ->whereKey($doctorId)
                                    ->where('branch_id', (int) $r->branch_id)
                                    ->where('is_active', true)
                                    ->exists();

                                if (! $doctorOk) {
                                    Notification::make()
                                        ->title('Cannot reschedule')
                                        ->body('Selected doctor is not available for this branch (moved or inactive).')
                                        ->danger()
                                        ->send();

                                    return;
                                }
                            }

                            // Optional but recommended: hard guard that slot actually exists
                            $service = app(AvailabilityService::class);
                            $slots = $service->timesFor(
                                (int) $r->branch_id,
                                $dateStr,
                                max(1, (int) ($r->party_size ?? 1)),
                                $doctorId
                            );

                            $slotValues = collect($slots)->pluck('value')->all();
                            if (! in_array($timeStr, $slotValues, true) && ! in_array(substr($timeStr, 0, 5), array_map(fn ($v) => substr((string) $v, 0, 5), $slotValues), true)) {
                                Notification::make()
                                    ->title('Time no longer available')
                                    ->body('Please pick another time slot.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // Single source-of-truth: BookingService
                            $booking = app(BookingService::class)->confirmFromHold(
                                [
                                    'branch_id' => (int) $r->branch_id,
                                    'res_date' => $dateStr,
                                    'res_time' => $timeStr,
                                    'party_size' => max(1, (int) ($r->party_size ?? 1)),
                                    'msisdn' => (string) ($r->msisdn ?? ''),
                                    'slot_key' => "{$dateStr}@{$timeStr}@".max(1, (int) ($r->party_size ?? 1))."@{$r->branch_id}",
                                    'source' => (string) ($r->source ?? 'admin'),
                                ],
                                [
                                    'existing_booking_id' => (int) $r->id,
                                    'branch_id' => (int) $r->branch_id,
                                    'doctor_id' => $doctorId,
                                    'table_id' => $r->table_id ? (int) $r->table_id : null,
                                    'patient_id' => $r->patient_id ? (int) $r->patient_id : null,
                                    'msisdn' => (string) ($r->msisdn ?? ''),
                                    'name' => (string) ($r->patient?->name ?? $r->contact?->name ?? ''),
                                    'locale' => app()->getLocale(),
                                    'agree_terms' => (bool) (($r->meta['contact_snapshot']['agree_terms'] ?? false)),
                                    'source' => 'admin',
                                    'source_ref' => 'filament:reschedule',
                                    'status' => (string) ($r->status ?? BookingResource::STATUS_CONFIRMED),
                                ]
                            );

                            $booking->refresh();
                            Notification::make()->title('Appointment rescheduled')->success()->send();
                        })
                        ->visible(fn (Booking $r) => ! self::isCheckedIn($r)
                            && ! self::isTerminal($r)
                            && in_array($r->status, [self::STATUS_CONFIRMED, self::STATUS_PENDING], true)
                            && (int) $r->branch_id > 0
                        ),

                    Tables\Actions\Action::make('assign_room')
                        ->label(fn (Booking $r) => $r->table_id ? 'Change Room' : 'Assign Room')
                        ->icon('heroicon-o-rectangle-stack')

                        // Tooltip so staff understand why they can’t change it
                        ->tooltip(function (Booking $r) {
                            $doctorId = (int) ($r->doctor_id ?? 0);
                            if (! $doctorId) {
                                return 'Assign a doctor first.';
                            }

                            $doctor = Doctor::query()->select('id', 'restaurant_table_id')->find($doctorId);
                            $doctorRoomId = (int) ($doctor?->restaurant_table_id ?? 0);

                            if (! $doctorRoomId) {
                                return 'Doctor has no fixed room. You can assign any available room in this branch.';
                            }

                            $currentRoomId = (int) ($r->table_id ?? 0);

                            if ($currentRoomId !== $doctorRoomId) {
                                return 'Doctor has a fixed room. Booking room is mismatched, you can only change it to the doctor’s room to keep reporting consistent.';
                            }

                            return 'Doctor has a fixed room. Room changes are locked to protect reporting.';
                        })
                        ->form([
                            Forms\Components\Select::make('table_id')
                                ->label('Room')
                                ->options(function (Booking $r) {
                                    $doctorId = (int) ($r->doctor_id ?? 0);
                                    $doctor = $doctorId
                                        ? Doctor::query()->select('id', 'restaurant_table_id')->find($doctorId)
                                        : null;

                                    $doctorRoomId = (int) ($doctor?->restaurant_table_id ?? 0);

                                    // If doctor has a fixed room:
                                    // - allow only doctor room when mismatch exists
                                    if ($doctorRoomId) {
                                        // Ensure doctor room is within same branch, otherwise return empty
                                        $room = RestaurantTable::query()
                                            ->where('branch_id', (int) $r->branch_id)
                                            ->where('id', $doctorRoomId)
                                            ->first();

                                        return $room ? [$room->id => $room->name] : [];
                                    }

                                    // Otherwise: normal options by branch
                                    return RestaurantTable::query()
                                        ->where('branch_id', (int) $r->branch_id)
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(function (Booking $r) {
                                    // Disable selector when doctor has fixed room AND booking already matches it
                                    $doctorId = (int) ($r->doctor_id ?? 0);
                                    if (! $doctorId) {
                                        return true;
                                    }

                                    $doctorRoomId = (int) (Doctor::query()->whereKey($doctorId)->value('restaurant_table_id') ?? 0);
                                    if (! $doctorRoomId) {
                                        return false;
                                    }

                                    $currentRoomId = (int) ($r->table_id ?? 0);

                                    // If already correct, lock the field
                                    return $currentRoomId === $doctorRoomId;
                                }),
                        ])
                        ->action(function (array $data, Booking $r) {
                            $doctorId = (int) ($r->doctor_id ?? 0);
                            if (! $doctorId) {
                                Notification::make()->title('Cannot change room')->body('Doctor is missing.')->danger()->send();

                                return;
                            }

                            $doctorRoomId = (int) (Doctor::query()->whereKey($doctorId)->value('restaurant_table_id') ?? 0);
                            $newTableId = (int) ($data['table_id'] ?? 0);

                            if (! $newTableId) {
                                Notification::make()->title('Cannot change room')->body('Room is required.')->danger()->send();

                                return;
                            }

                            // Validate room belongs to branch
                            $roomInBranch = RestaurantTable::query()
                                ->where('branch_id', (int) $r->branch_id)
                                ->where('id', $newTableId)
                                ->exists();

                            if (! $roomInBranch) {
                                Notification::make()->title('Cannot change room')->body('Selected room is not in this branch.')->danger()->send();

                                return;
                            }

                            // If doctor has fixed room, only allow setting to doctor room when mismatch exists
                            if ($doctorRoomId) {
                                $currentRoomId = (int) ($r->table_id ?? 0);

                                if ($currentRoomId === $doctorRoomId) {
                                    // Already correct; block changes
                                    Notification::make()->title('Room change not allowed')->body('Doctor has a fixed room.')->warning()->send();

                                    return;
                                }

                                if ($newTableId !== $doctorRoomId) {
                                    Notification::make()
                                        ->title('Room change not allowed')
                                        ->body('Doctor has a fixed room. You can only assign the doctor’s room to keep reports consistent.')
                                        ->danger()
                                        ->send();

                                    return;
                                }
                            }

                            // Proceed with your existing transaction (kept intact)
                            DB::transaction(function () use ($newTableId, $r) {
                                $oldTableId = $r->table_id;

                                $r->update(['table_id' => $newTableId]);

                                Visit::where('booking_id', $r->id)
                                    ->update(['restaurant_table_id' => $newTableId]);

                                if (! is_null($r->checked_in_at)) {
                                    if ($oldTableId && $oldTableId !== $newTableId) {
                                        RestaurantTable::where('id', $oldTableId)->update(['status' => 'available']);
                                    }
                                    RestaurantTable::where('id', $newTableId)->update(['status' => 'occupied']);
                                }
                            });

                            $r->refresh();
                            Notification::make()->title('Room assigned')->success()->send();
                        })
                        ->visible(fn (Booking $r) => ! self::isTerminal($r)
                            && ! self::isCheckedIn($r)
                            && (int) $r->branch_id > 0
                        ),

                    Tables\Actions\Action::make('resend')
                        ->label('Resend')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->action(function (Booking $r) {

                            // Normalize MSISDN (avoid formatted numbers breaking sends)
                            $msisdn = preg_replace('/\D+/', '', (string) ($r->msisdn ?? ''));
                            if (! $msisdn) {
                                Notification::make()->title('No phone number')->danger()->send();

                                return;
                            }

                            app(\App\Services\QrPassService::class)->ensureToken($r);
                            $qrUrl = route('bookings.qr', ['token' => $r->qr_token]);
                            $passUrl = app(\App\Services\QrPassService::class)->passUrl($r);

                            $tz = config('app.timezone', 'Asia/Kuwait');
                            [$start, $end] = self::resolveSlot($r, $tz);

                            // Template values
                            $dateTpl = $start ? $start->isoFormat('ddd, D MMM YYYY') : '—'; // {{1}}
                            $timeTpl = $start ? $start->format('H:i') : '—';               // {{2}}

                            // Legacy caption values
                            $date = $start ? $start->isoFormat('ddd, D MMM') : '—';
                            $time = $start ? $start->format('h:i A').($end ? '–'.$end->format('h:i A') : '') : '—';

                            $code = (string) ($r->booking_code ?? '');
                            $text = "Appointment Confirmed\nCode: {$code}\nDate: {$date}\nTime: {$time}\n\nYour QR Pass:\n{$passUrl}";

                            $wa = app(\App\Services\WhatsAppSender::class);

                            // Locale selection (safe fallback)
                            $locale = app()->getLocale();
                            $lang = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';

                            // Optional override to force a specific language (recommended to force 'en' utility if needed)
                            $forcedLang = (string) config('services.whatsapp.confirm_lang', '');
                            if ($forcedLang !== '' && in_array($forcedLang, ['ar', 'en'], true)) {
                                $lang = $forcedLang;
                            }

                            $sent = false;

                            // 1) Template first (prevents 131047)
                            try {
                                $sent = $wa->sendClinicConfirmedV3(
                                    $msisdn,
                                    $lang,
                                    $qrUrl,
                                    $dateTpl,
                                    $timeTpl,
                                    $code,
                                    $passUrl
                                );
                            } catch (\Throwable) {
                                $sent = false;
                            }

                            // 2) Fallback: legacy image+caption
                            if (! $sent) {
                                try {
                                    $sent = $wa->sendImage($msisdn, $qrUrl, $text);
                                } catch (\Throwable) {
                                    $sent = false;
                                }
                            }

                            // 3) Final fallback: plain text
                            if (! $sent) {
                                try {
                                    $wa->sendTextMessage($msisdn, $text);
                                    $sent = true;
                                } catch (\Throwable) {
                                    $sent = false;
                                }
                            }

                            $note = Notification::make()->title($sent ? 'Confirmation resent' : 'Confirmation resend failed');
                            $sent ? $note->success() : $note->danger();
                            $note->send();
                        })
                        ->visible(fn (Booking $r) => ! self::isCheckedIn($r)
                            && ! self::isTerminal($r)
                            && $r->status === self::STATUS_CONFIRMED
                            && filled($r->msisdn)
                            && filled($r->booking_code)
                        ),

                    Tables\Actions\Action::make('collect_consultation')
                        ->label('Collect Consultation')
                        ->icon('heroicon-o-banknotes')
                        ->color('primary')
                        ->visible(fn (Booking $r) => ! self::isCheckedIn($r)
                            && ! self::isTerminal($r)
                            && $r->status === self::STATUS_CONFIRMED
                            && self::hasCoreIds($r)
                            && ! self::hasConsultationPaid($r)
                            && self::consultationFeeAmountForDoctor((int) $r->doctor_id) > 0
                        )
                        ->form([
                            Forms\Components\TextInput::make('fee')
                                ->label('Consultation fee (from doctor)')
                                ->disabled()
                                ->dehydrated(false)
                                ->default(fn (\App\Models\Booking $r) => number_format(self::consultationFeeAmountForDoctor((int) $r->doctor_id), 3)),

                            // FIX: Added Manual POS Options + Dynamic Gateways
                            Forms\Components\Select::make('method')
                                ->label('Payment method')
                                ->live()
                                ->options(fn (Booking $record) => \App\Models\GatewayAccount::paymentOptionsForBookingWithFallback($record))
                                ->required()
                                ->default(function (Booking $record) {
                                    $opts = \App\Models\GatewayAccount::paymentOptionsForBookingWithFallback($record);

                                    // Prefer knet when present, otherwise first option, otherwise fallback
                                    if (array_key_exists('knet', $opts)) {
                                        return 'knet';
                                    }

                                    $first = array_key_first($opts);

                                    return $first ?: 'knet';
                                }),

                            // 2. Only show "Mark as Paid" for Manual/POS methods.
                            // If "Link", it stays unpaid until the callback.
                            Forms\Components\Toggle::make('mark_paid')
                                ->label('Mark as paid now')
                                ->default(true)
                                ->visible(fn (Forms\Get $get) => $get('method') !== 'link'),

                            Forms\Components\TextInput::make('reference_no')
                                ->label('Reference / Receipt No')
                                ->maxLength(64)
                                ->visible(fn (Forms\Get $get) => $get('method') !== 'link'),
                        ])
                        ->action(function (array $data, \App\Models\Booking $r) {
                            $amount = self::consultationFeeAmountForDoctor((int) $r->doctor_id);
                            if ($amount <= 0) {
                                Notification::make()->title('Fee Missing')->body('Doctor has no consultation fee set.')->danger()->send();

                                return;
                            }

                            try {
                                DB::transaction(function () use ($data, $r, $amount) {
                                    // 1. Ensure Visit Exists (Matches robust logic)
                                    $visit = self::ensureVisitForBooking($r, now());

                                    // 2. Add/Update Charge (Invoice is created regardless of payment method)
                                    $label = self::consultationLabel();
                                    \App\Models\VisitCharge::updateOrCreate(
                                        ['visit_id' => $visit->id, 'label' => $label],
                                        [
                                            'branch_id' => (int) $visit->branch_id,
                                            'qty' => 1,
                                            'unit_price_snapshot' => $amount,
                                            'line_total' => $amount,
                                            'added_by_user_id' => auth()->id() ?? 0,
                                        ]
                                    );

                                    // 3. Handle Payment Method
                                    if ($data['method'] === 'link') {
                                        // A. SEND LINK (Do NOT mark as paid yet)

                                        // Find best Gateway Account (Logic reused from options)
                                        $branchId = $r->branch_id;
                                        $partnerId = $r->branch?->partner_id ?? \App\Models\Branch::find($branchId)?->partner_id;

                                        $gatewayAccount = \App\Models\GatewayAccount::query()
                                            ->withoutGlobalScopes() // IMPORTANT: allow system/partner rows even if branch scope exists
                                            ->where('is_active', true)
                                            ->whereHas('gateway', fn ($q) => $q->where('driver', 'myfatoorah'))
                                            ->where(function ($q) use ($branchId, $partnerId) {
                                                $q->where(function ($sq) use ($branchId) {
                                                    $sq->where('owner_type', 'branch')->where('branch_id', $branchId);
                                                });

                                                if ($partnerId > 0) {
                                                    $q->orWhere(function ($sq) use ($partnerId) {
                                                        $sq->where('owner_type', 'partner')->where('partner_id', $partnerId);
                                                    });
                                                }

                                                $q->orWhere('owner_type', 'system');
                                            })
                                            ->orderByRaw("FIELD(owner_type, 'branch', 'partner', 'system')")
                                            ->orderByDesc('id')
                                            ->first();

                                        if (! $gatewayAccount || empty(data_get($gatewayAccount->credentials, 'api_key'))) {
                                            throw new \Exception('No valid MyFatoorah API key configured for this clinic (branch/partner/system).');
                                        }

                                        // Create Invoice
                                        $mf = new \App\Services\Payment\MyFatoorahService($gatewayAccount->credentials);
                                        $link = $mf->createInvoice([
                                            'amount' => $amount,
                                            'name' => $r->patient_display,
                                            'phone' => $r->msisdn,
                                            'ref_id' => 'BKG-'.$r->booking_code,
                                            'account_id' => $gatewayAccount->id, // Important for callback
                                        ]);

                                        // Send WhatsApp
                                        $wa = app(\App\Services\WhatsAppSender::class);

                                        // Template Name: payment_request_utility (Create this in Meta Manager!)
                                        // Variables: {{1}}=Name, {{2}}=Amount, {{3}}=Link
                                        $templateName = 'payment_request_utility';
                                        $lang = app()->getLocale(); // 'en' or 'ar'

                                        // Structure parameters for the Body Component
                                        $bodyParams = [
                                            ['type' => 'text', 'text' => $r->patient_display ?? 'Valued Patient'],
                                            ['type' => 'text', 'text' => $amount.' KD'],
                                            ['type' => 'text', 'text' => $link],
                                        ];

                                        $wa->sendTemplate($r->msisdn, $templateName, $lang, $bodyParams);

                                    } elseif ((bool) ($data['mark_paid'] ?? true)) {
                                        // B. MANUAL PAYMENT (Cash/KNET POS)

                                        $kind = \App\Models\VisitPayment::KIND_CONSULTATION ?? 'consultation';
                                        $exists = \App\Models\VisitPayment::where('visit_id', $visit->id)
                                            ->where('kind', $kind)->exists();

                                        if (! $exists) {
                                            \App\Models\VisitPayment::create([
                                                'visit_id' => $visit->id,
                                                'kind' => $kind,
                                                'amount' => $amount,
                                                'method' => (string) ($data['method'] ?? 'cash'),
                                                'status' => 'paid',
                                                'reference_no' => $data['reference_no'] ?? null,
                                                'collected_by_user_id' => auth()->id() ?? 0,
                                                'paid_at' => now(),
                                            ]);
                                        }
                                    }
                                });

                                if ($data['method'] === 'link') {
                                    Notification::make()->title('Payment Link Sent')->body('The patient has received the link via WhatsApp.')->success()->send();
                                } else {
                                    Notification::make()->title('Consultation Recorded')->success()->send();
                                }

                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('print_receipt')
                        ->label('Print Receipt')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->url(fn (Booking $r) => route('bookings.receipt.show', $r->id))
                        ->openUrlInNewTab()
                        ->visible(fn (Booking $r) => self::hasConsultationPaid($r)
                            && filled($r->booking_code)
                        ),

                    Tables\Actions\Action::make('check_in')
                        ->label('Check-in')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->tooltip(function (Booking $r) {
                            $tz = config('app.timezone', 'Asia/Kuwait');
                            $now = Carbon::now($tz);

                            $windowMinutes = max(1, (int) config('clinic.checkin_window_minutes', 60));

                            // Resolve appointment start (prefer res_start, fallback to res_date + res_time)
                            $start = $r->res_start ? $r->res_start->copy()->timezone($tz) : null;

                            if (! $start) {
                                $dateRaw = $r->res_date;
                                $datePart = ($dateRaw instanceof \DateTimeInterface)
                                    ? $dateRaw->format('Y-m-d')
                                    : (explode(' ', trim((string) $dateRaw))[0] ?? '');

                                $timeRaw = $r->res_time;
                                $timePart = ($timeRaw instanceof \DateTimeInterface)
                                    ? $timeRaw->format('H:i:s')
                                    : trim((string) $timeRaw);

                                if ($datePart === '' || $timePart === '') {
                                    return 'Check-in allowed only within ±'.$windowMinutes.' minutes of the appointment start. Appointment schedule is missing date/time.';
                                }

                                if (preg_match('/^\d{2}:\d{2}$/', $timePart)) {
                                    $timePart .= ':00';
                                }

                                try {
                                    $start = Carbon::parse("{$datePart} {$timePart}", $tz)->seconds(0);
                                } catch (\Throwable) {
                                    return 'Check-in allowed only within ±'.$windowMinutes.' minutes of the appointment start. Appointment date/time is invalid.';
                                }
                            }

                            $early = $start->copy()->subMinutes($windowMinutes);
                            $late = $start->copy()->addMinutes($windowMinutes);

                            // If currently allowed, keep tooltip simple (still informative)
                            if (! ($now->lt($early) || $now->gt($late))) {
                                return 'Check-in window is open. Allowed within ±'.$windowMinutes.' minutes of the appointment start.';
                            }

                            return
                                'Check-in allowed only within ±'.$windowMinutes." minutes of the appointment start.\n".
                                'Appointment: '.$start->format('Y-m-d h:i A')."\n".
                                'Allowed window: '.$early->format('h:i A').' – '.$late->format('h:i A');
                        })

                        ->visible(fn (Booking $r) => ! self::isCheckedIn($r)
                            && ! self::isTerminal($r)
                            && $r->status === self::STATUS_CONFIRMED
                            && self::hasCoreIds($r)
                            && self::hasVisit($r)
                            && self::hasConsultationCharge($r)
                            && self::hasConsultationPaid($r)
                            && (int) $r->table_id > 0
                        )

                        ->requiresConfirmation()
                        ->action(function (Booking $r) {
                            $tz = config('app.timezone', 'Asia/Kuwait');
                            $now = Carbon::now($tz);

                            // 1) Hard guards: required relations
                            if (! $r->patient_id || ! $r->doctor_id) {
                                Notification::make()
                                    ->title('Check-in Failed')
                                    ->body('Missing Patient or Doctor ID.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // 2) Schedule guard: must have date + time
                            $dateRaw = $r->res_date;
                            $datePart = ($dateRaw instanceof \DateTimeInterface)
                                ? $dateRaw->format('Y-m-d')
                                : (explode(' ', trim((string) $dateRaw))[0] ?? '');

                            $timeRaw = $r->res_time;
                            $timePart = ($timeRaw instanceof \DateTimeInterface)
                                ? $timeRaw->format('H:i:s')
                                : trim((string) $timeRaw);

                            if ($datePart === '' || $timePart === '') {
                                Notification::make()
                                    ->title('Check-in Not Allowed')
                                    ->body('Appointment schedule is missing date/time. Please reschedule first.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // Normalize time to HH:MM:SS
                            if (preg_match('/^\d{2}:\d{2}$/', $timePart)) {
                                $timePart .= ':00';
                            }

                            // 3) Compute appointment start safely (prefer res_start if present)
                            $start = $r->res_start ? $r->res_start->copy()->timezone($tz) : null;

                            if (! $start) {
                                try {
                                    $start = Carbon::parse("{$datePart} {$timePart}", $tz)->seconds(0);
                                } catch (\Throwable) {
                                    Notification::make()
                                        ->title('Check-in Not Allowed')
                                        ->body('Invalid appointment date/time. Please reschedule.')
                                        ->danger()
                                        ->send();

                                    return;
                                }
                            }

                            // 4) ± window rule (configurable)
                            $default = max(1, (int) config('clinic.checkin_window_minutes', 60)); // legacy fallback

                            $beforeMinutes = (int) config('clinic.checkin_window_before_minutes', $default);
                            $afterMinutes = (int) config('clinic.checkin_window_after_minutes', $default);

                            $beforeMinutes = max(1, $beforeMinutes);
                            $afterMinutes = max(1, $afterMinutes);

                            $early = $start->copy()->subMinutes($beforeMinutes);
                            $late = $start->copy()->addMinutes($afterMinutes);

                            $windowText = $beforeMinutes === $afterMinutes
                                ? '±'.$beforeMinutes.' minutes'
                                : $beforeMinutes.' minutes before / '.$afterMinutes.' minutes after';

                            if ($now->lt($early) || $now->gt($late)) {
                                Notification::make()
                                    ->title('Check-in Not Allowed')
                                    ->body(
                                        'Check-in is allowed only within '.$windowText." of the appointment.\n".
                                        'Appointment: '.$start->format('Y-m-d h:i A')."\n".
                                        'Allowed window: '.$early->format('h:i A').' – '.$late->format('h:i A')
                                    )
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            // 5) Ensure code exists (same as your logic)
                            if (empty($r->booking_code)) {
                                $r->update(['booking_code' => strtoupper(Str::random(6))]);
                                $r->refresh();
                            }

                            // Consultation gate: must be recorded BEFORE check-in
                            $visit = \App\Models\Visit::query()->where('booking_id', $r->id)->first();

                            $label = self::consultationLabel();

                            $hasCharge = $visit
                                ? \App\Models\VisitCharge::query()
                                    ->where('visit_id', $visit->id)
                                    ->where('label', $label)
                                    ->exists()
                                : false;

                            if (! $hasCharge) {
                                Notification::make()
                                    ->title('Cannot Check-in')
                                    ->body('Consultation fee must be collected before check-in.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            // If you want STRICT paid-before-checkin (recommended for reception workflow):
                            $paid = $visit
                                    ? (float) \App\Models\VisitPayment::query()
                                        ->where('visit_id', $visit->id)
                                        ->where('kind', \App\Models\VisitPayment::KIND_CONSULTATION)
                                        ->where('status', 'paid')
                                        ->sum('amount')
                                    : 0.0;

                            if ($paid <= 0.000) {
                                Notification::make()
                                    ->title('Cannot Check-in')
                                    ->body('Consultation fee must be paid before check-in.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            // 6) Atomic Transaction (ROOM -> BOOKING -> VISIT)
                            try {
                                DB::transaction(function () use ($r, $now) {

                                    // A) Room guard FIRST (prevents partial check-in)
                                    if ($r->table_id) {
                                        $table = RestaurantTable::query()
                                            ->lockForUpdate()
                                            ->whereKey($r->table_id)
                                            ->first();

                                        if (! $table) {
                                            throw new \RuntimeException('Room not found.');
                                        }

                                        // if (($table->status ?? null) !== 'available') {
                                        //     throw new \RuntimeException("Room {$table->name} is not available.");
                                        // }

                                        $table->update(['status' => 'occupied']);
                                    }

                                    // B) Booking check-in
                                    $r->update([
                                        'checked_in_at' => $now,
                                        'status' => self::STATUS_CONFIRMED,
                                    ]);

                                    // C) Visit queue (NO service_started_at)
                                    $visit = Visit::firstOrNew(['booking_id' => $r->id]);

                                    $visit->fill([
                                        'patient_id' => $r->patient_id,
                                        'doctor_id' => $r->doctor_id,
                                        'branch_id' => $r->branch_id,
                                        'restaurant_table_id' => $r->table_id,
                                        'source' => $r->source,
                                        'booking_code' => $r->booking_code,
                                        'notes' => trim("Booking code: {$r->booking_code}\nSource: ".($r->source ?? '')),

                                        'checked_in_at' => $visit->checked_in_at ?? $now,

                                        // If you added queued_at column, keep this:
                                        'queued_at' => $visit->queued_at ?? $now,
                                    ]);

                                    // Always queue at check-in (do not rely on "new visit only")
                                    // Optional safety: don't re-queue completed/no_show visits
                                    if (! in_array(($visit->status ?? null), ['completed', 'no_show', 'cancelled'], true)) {
                                        $visit->status = 'awaiting_doctor';
                                    }

                                    $visit->save();
                                });

                            } catch (\Throwable $e) {
                                report($e);

                                Notification::make()
                                    ->title('Check-in Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $r->refresh();

                            Notification::make()
                                ->title('Patient Checked In & Queued')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('collect_visit_payment')
                        ->label('Collect Payment')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->modalHeading('Collect Payment')
                        ->modalDescription('Record a payment for this visit without leaving this page.')
                        ->modalSubmitActionLabel('Save Payment')
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount')
                                ->numeric()
                                ->step('0.001')
                                ->required()
                                ->default(function (Booking $r) {
                                    $visit = Visit::query()->where('booking_id', $r->id)->with('payments')->first();
                                    if (! $visit) {
                                        return 0;
                                    }

                                    try {
                                        return app(VisitCostingService::class)->getRemainingBalance($visit);
                                    } catch (\Throwable) {
                                        return 0;
                                    }
                                }),

                            Forms\Components\Select::make('method')
                                ->label('Method')
                                ->native(false)
                                ->required()
                                ->options(fn (Booking $r) => \App\Models\GatewayAccount::paymentOptionsForBookingWithFallback($r))
                                ->default(function (Booking $r) {
                                    $opts = \App\Models\GatewayAccount::paymentOptionsForBookingWithFallback($r);
                                    if (array_key_exists('knet', $opts)) {
                                        return 'knet';
                                    }

                                    return array_key_first($opts) ?: 'cash';
                                }),

                            Forms\Components\Select::make('kind')
                                ->label('Type')
                                ->native(false)
                                ->required()
                                ->options([
                                    'consultation' => 'Consultation',
                                    'services' => 'Services',
                                    'medicines' => 'Medicines',
                                    'other' => 'Other',
                                ])
                                ->default('consultation'),

                            Forms\Components\TextInput::make('reference_no')
                                ->label('Reference / Receipt No')
                                ->maxLength(191),

                            Forms\Components\Toggle::make('mark_paid')
                                ->label('Mark as paid now')
                                ->default(true)
                                ->visible(fn (Forms\Get $get) => $get('method') !== 'link'),

                            Forms\Components\DateTimePicker::make('paid_at')
                                ->label('Paid At')
                                ->seconds(false)
                                ->visible(fn (Forms\Get $get) => (bool) $get('mark_paid'))
                                ->nullable(),
                        ])
                        ->action(function (array $data, Booking $r) {

                            DB::transaction(function () use ($data, $r) {

                                // Ensure visit exists (safe default)
                                $visit = Visit::firstOrCreate(
                                    ['booking_id' => $r->id],
                                    [
                                        'patient_id' => $r->patient_id,
                                        'doctor_id' => $r->doctor_id,
                                        'branch_id' => $r->branch_id,
                                        'restaurant_table_id' => $r->table_id,
                                        'source' => $r->source,
                                        'booking_code' => $r->booking_code,
                                        'status' => Visit::STATUS_CREATED ?? 'created',
                                    ]
                                );

                                // Keep the same “disabled amount saved” behavior as your RelationManager
                                $status = ($data['method'] ?? null) === 'link'
                                    ? 'pending'
                                    : (($data['mark_paid'] ?? true) ? 'paid' : 'pending');

                                VisitPayment::create([
                                    'visit_id' => (int) $visit->id,
                                    'kind' => (string) ($data['kind'] ?? 'consultation'),
                                    'amount' => (float) ($data['amount'] ?? 0),
                                    'method' => (string) ($data['method'] ?? 'cash'),
                                    'status' => $status,
                                    'reference_no' => $data['reference_no'] ?? null,
                                    'collected_by_user_id' => (int) (auth()->id() ?? 0) ?: null,
                                    'paid_at' => $status === 'paid'
                                        ? ($data['paid_at'] ?? now())
                                        : null,
                                ]);

                                // Recompute snapshot totals so discharge sees latest
                                if (config('clinic.visit_financials_enabled', false)) {
                                    app(VisitCostingService::class)->compute($visit, (int) (auth()->id() ?? 0));
                                }
                            });

                            Notification::make()
                                ->title('Payment recorded')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Booking $r) => ! self::isTerminal($r)
                            && self::hasVisit($r)
                            && self::visitIsOpen($r)
                            && self::isCheckedIn($r)
                        ),

                    Tables\Actions\Action::make('discharge')
                        ->label('Discharge')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('danger') // Changed to danger to indicate point of no return
                        ->visible(fn (Booking $r) => self::isCheckedIn($r)
                            && ! self::isTerminal($r)
                            && self::hasVisit($r)
                            && self::hasConsultationPaid($r)
                            && self::visitIsOpen($r)
                            && (
                                ! config('clinic.visit_financials_enabled', false)
                                || self::visitIsFullyPaidForBooking($r)
                            )
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Discharge Patient')
                        ->modalDescription('Are you sure? This will verify payments and close the visit.')
                        ->action(function (\Filament\Tables\Actions\Action $action, Booking $r) {

                            // A. Financial Guard -----------------------------------------
                            $visit = Visit::where('booking_id', $r->id)->first();

                            if ($visit) {
                                try {
                                    // 1. Force Recompute Snapshot (Safety First)
                                    app(\App\Services\Clinic\VisitCostingService::class)->compute($visit, (int) (auth()->id() ?? 0));
                                    $visit->refresh(); // Load new totals

                                    // 2. Load Payments
                                    $visit->load('payments');

                                    // 3. Calculate Balance
                                    // Null Defense: Default to 0.0 if column is null
                                    $totalCost = (float) ($visit->fees_total ?? 0);
                                    $totalPaid = (float) \App\Models\VisitPayment::query()
                                        ->where('visit_id', $visit->id)
                                        ->where('status', 'paid')
                                        ->sum('amount');
                                    $balance = $totalCost - $totalPaid;

                                    // 4. Strict Check (Floating point tolerance)
                                    if ($balance > 0.005) {
                                        Notification::make()
                                            ->title('Cannot Discharge: Payment Pending')
                                            ->body('Outstanding Balance: '.number_format($balance, 3).' KD. Please collect payment first.')
                                            ->danger()
                                            ->persistent() // Force user to dismiss
                                            ->send();

                                        $action->halt(); // Stop execution immediately

                                        return;
                                    }

                                } catch (Halt $e) {
                                    // IMPORTANT: let Filament handle it (do not convert it to "System Error")
                                    throw $e;
                                } catch (\Throwable $e) {
                                    report($e);

                                    Notification::make()
                                        ->title('System Error')
                                        ->body('Could not verify financials. Check logs.')
                                        ->danger()
                                        ->send();

                                    return;
                                }
                            }
                            // ------------------------------------------------------------

                            // B. Legacy Discharge Logic (Preserved) ----------------------
                            DB::transaction(function () use ($r) {
                                if ($r->table_id) {
                                    RestaurantTable::where('id', $r->table_id)->update(['status' => 'available']);
                                }

                                $now = Carbon::now(config('app.timezone', 'Asia/Kuwait'));

                                $meta = (array) $r->meta;
                                $meta['checked_out_at'] = $now->toDateTimeString();

                                $r->update([
                                    'meta' => $meta,
                                    'checked_in_at' => null, // Legacy behavior: unsets check-in on complete
                                    'status' => self::STATUS_COMPLETED,
                                ]);

                                Visit::where('booking_id', $r->id)
                                    ->whereNull('completed_at')
                                    ->update([
                                        'service_started_at' => DB::raw('COALESCE(service_started_at, NOW())'),
                                        'completed_at' => $now,
                                        'status' => 'completed',
                                    ]);
                            });

                            $r->refresh();
                            Notification::make()
                                ->title('Discharged')
                                ->body("Appointment {$r->booking_code} completed.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('mark_no_show')
                        ->label('No-show')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->visible(fn (Booking $r) => ! self::isTerminal($r)
                            && $r->status === self::STATUS_CONFIRMED
                            && ! self::isCheckedIn($r)
                            && $r->res_end
                            && $r->res_end->isPast()
                        )
                        ->requiresConfirmation()
                        ->action(function (Booking $r) {
                            DB::transaction(function () use ($r) {
                                $now = Carbon::now(config('app.timezone', 'Asia/Kuwait'));

                                $meta = (array) $r->meta;
                                $meta['no_show'] = true;
                                $meta['closed_at'] = $now->toDateTimeString();

                                $r->update([
                                    'status' => self::STATUS_CANCELLED,
                                    'meta' => $meta,
                                ]);

                                if ($r->table_id) {
                                    RestaurantTable::where('id', $r->table_id)->update(['status' => 'available']);
                                }

                                Visit::where('booking_id', $r->id)
                                    ->update([
                                        'status' => 'no_show',
                                        'completed_at' => $now,
                                    ]);
                            });

                            $r->refresh();
                            Notification::make()->title('Marked as no-show')->success()->send();
                        }),

                    Tables\Actions\Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Booking $r) => ! self::isCheckedIn($r)
                            && ! self::isTerminal($r)
                        )
                        ->requiresConfirmation()
                        ->action(function (Booking $r) {
                            if ($r->checked_in_at) {
                                Notification::make()->title('Cannot cancel checked-in appointment')->danger()->send();

                                return;
                            }

                            $r->update(['status' => self::STATUS_CANCELLED]);
                            $r->refresh();
                            Notification::make()->title('Appointment cancelled')->success()->send();
                        }),
                    Tables\Actions\Action::make('visit_items')
                        ->label('Visit: Items / Services')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->url(function (Booking $r) {
                            $visitId = Visit::query()->where('booking_id', $r->id)->value('id');
                            if (! $visitId) {
                                return null;
                            }

                            return VisitResource::getUrl('edit', ['record' => $visitId])
                            .'?activeRelationManager=0&scrollTo=relations';
                        })
                        ->openUrlInNewTab()
                        ->visible(fn (Booking $r) => self::hasVisit($r) && self::visitIsOpen($r)),

                    Tables\Actions\Action::make('visit_payments')
                        ->label('Visit: Payments')
                        ->icon('heroicon-o-banknotes')
                        ->url(function (Booking $r) {
                            $visitId = Visit::query()->where('booking_id', $r->id)->value('id');
                            if (! $visitId) {
                                return null;
                            }

                            return VisitResource::getUrl('edit', ['record' => $visitId])
                            .'?activeRelationManager=1&scrollTo=relations';
                        })
                        ->openUrlInNewTab()
                        ->visible(fn (Booking $r) => self::hasVisit($r) && self::visitIsOpen($r)),

                    Tables\Actions\Action::make('visit_followups')
                        ->label('Visit: Follow-ups')
                        ->icon('heroicon-o-arrow-path')
                        ->url(function (Booking $r) {
                            $visitId = Visit::query()->where('booking_id', $r->id)->value('id');
                            if (! $visitId) {
                                return null;
                            }

                            return VisitResource::getUrl('edit', ['record' => $visitId])
                            .'?activeRelationManager=2&scrollTo=relations';
                        })
                        ->openUrlInNewTab()
                        ->visible(fn (Booking $r) => self::hasVisit($r) && self::visitIsOpen($r)),
                ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_resend')
                        ->label('Resend confirmations')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->action(function ($records) {

                            $tz = config('app.timezone', 'Asia/Kuwait');

                            $wa = app(\App\Services\WhatsAppSender::class);
                            $qrPass = app(\App\Services\QrPassService::class);

                            // Locale selection once
                            $locale = app()->getLocale();
                            $lang = in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
                            $forcedLang = (string) config('services.whatsapp.confirm_lang', '');
                            if ($forcedLang !== '' && in_array($forcedLang, ['ar', 'en'], true)) {
                                $lang = $forcedLang;
                            }

                            $sentCount = 0;
                            $skippedCount = 0;
                            $failedCount = 0;

                            foreach ($records as $r) {
                                /** @var \App\Models\Booking $r */
                                if ($r->status !== self::STATUS_CONFIRMED || ! is_null($r->checked_in_at)) {
                                    $skippedCount++;

                                    continue;
                                }

                                $msisdn = preg_replace('/\D+/', '', (string) ($r->msisdn ?? ''));
                                if (! $msisdn) {
                                    $skippedCount++;

                                    continue;
                                }

                                try {
                                    $qrPass->ensureToken($r);

                                    $qrUrl = route('bookings.qr', ['token' => $r->qr_token]);
                                    $passUrl = $qrPass->passUrl($r);

                                    [$start, $end] = self::resolveSlot($r, $tz);

                                    $dateTpl = $start ? $start->isoFormat('ddd, D MMM YYYY') : '—';
                                    $timeTpl = $start ? $start->format('H:i') : '—';

                                    $date = $start ? $start->isoFormat('ddd, D MMM') : '—';
                                    $time = $start ? $start->format('h:i A').($end ? '–'.$end->format('h:i A') : '') : '—';

                                    $code = (string) ($r->booking_code ?? '');
                                    $text = "Appointment Confirmed\nCode: {$code}\nDate: {$date}\nTime: {$time}\n\nYour QR Pass:\n{$passUrl}";

                                    $sent = false;

                                    // Template first
                                    try {
                                        $sent = $wa->sendClinicConfirmedV3(
                                            $msisdn,
                                            $lang,
                                            $qrUrl,
                                            $dateTpl,
                                            $timeTpl,
                                            $code,
                                            $passUrl
                                        );
                                    } catch (\Throwable) {
                                        $sent = false;
                                    }

                                    // Fallback: image
                                    if (! $sent) {
                                        try {
                                            $sent = $wa->sendImage($msisdn, $qrUrl, $text);
                                        } catch (\Throwable) {
                                            $sent = false;
                                        }
                                    }

                                    // Final fallback: text
                                    if (! $sent) {
                                        try {
                                            $wa->sendTextMessage($msisdn, $text);
                                            $sent = true;
                                        } catch (\Throwable) {
                                            $sent = false;
                                        }
                                    }

                                    if ($sent) {
                                        $sentCount++;
                                    } else {
                                        $failedCount++;
                                    }

                                } catch (\Throwable) {
                                    $failedCount++;

                                    continue;
                                }
                            }

                            $title = "Confirmations processed: sent {$sentCount}";
                            if ($skippedCount > 0) {
                                $title .= ", skipped {$skippedCount}";
                            }
                            if ($failedCount > 0) {
                                $title .= ", failed {$failedCount}";
                            }

                            Notification::make()
                                ->title($title)
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_cancel')
                        ->label('Cancel selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $eligible = $records->filter(function ($record) {
                                return is_null($record->checked_in_at) &&
                                    ! in_array($record->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
                            });

                            $skipped = $records->count() - $eligible->count();

                            $eligible->each->update(['status' => self::STATUS_CANCELLED]);

                            Notification::make()
                                ->title('Bulk cancel complete')
                                ->body("Cancelled: {$eligible->count()} | Skipped: {$skipped}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No appointments yet')
            ->emptyStateDescription('Create an appointment or wait for WhatsApp bookings to come in.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Create Appointment'),
            ]);
    }

    // Recommendation 2: Centralized slot calculation to avoid logic drift
    protected static function calculateSlotEnd($date, $time, $branchId): ?Carbon
    {
        if (! $date || ! $time || ! $branchId) {
            return null;
        }

        $tz = config('app.timezone', 'Asia/Kuwait');

        $dateStr = ($date instanceof \DateTimeInterface)
            ? $date->format('Y-m-d')
            : substr((string) $date, 0, 10);

        // Sanitize time input if it comes in as string with seconds or without
        $timeStr = ($time instanceof \DateTimeInterface) ? $time->format('H:i:s') : (string) $time;

        try {
            $start = Carbon::parse("{$dateStr} {$timeStr}", $tz)->seconds(0);
        } catch (\Exception $e) {
            return null;
        }

        // Fetch rule for slot length
        // Note: unique constraint exists on branch_id + day_of_week, so first() is safe.
        $rule = BranchAvailabilityRule::where('branch_id', $branchId)
            ->where('day_of_week', $start->dayOfWeek)
            ->first();

        // ALIGNED WITH AvailabilityService:
        // Service uses logic: $step = (int) ($rule->slot_length_minutes ?: $rule->slot_step_minutes ?: config('booking.slot_interval', 30));
        // We replicate that here to ensure the end time stored matches what the service calculated for the slots.
        $minutes = (int) ($rule?->slot_length_minutes ?? $rule?->slot_step_minutes ?? config('booking.slot_interval', 30));

        // Safety clamp from Service logic
        $minutes = max(5, $minutes);

        return $start->copy()->addMinutes($minutes)->seconds(0);
    }

    protected static function resolveSlot(\App\Models\Booking $r, string $tz): array
    {
        $start = $r->res_start;
        $end = $r->res_end;

        if (! $start) {
            $dateRaw = $r->res_date;
            $datePart = ($dateRaw instanceof \DateTimeInterface) ? $dateRaw->format('Y-m-d') : explode(' ', trim((string) $dateRaw))[0] ?? '';
            $timeRaw = $r->res_time;
            $timePart = ($timeRaw instanceof \DateTimeInterface) ? $timeRaw->format('H:i:s') : trim((string) $timeRaw);

            if ($datePart !== '' && $timePart !== '') {
                try {
                    $start = Carbon::parse("$datePart $timePart", $tz)->seconds(0);
                } catch (\Throwable) {
                    $start = null;
                }
            }
        }

        if (! $end && $start) {
            $dow = (int) $start->format('w');
            $rule = BranchAvailabilityRule::where('branch_id', $r->branch_id)->where('day_of_week', $dow)->first();

            // Align with Service logic here too
            $len = (int) ($rule?->slot_length_minutes ?? $rule?->slot_step_minutes ?? config('booking.slot_interval', 30));
            $len = max(5, $len);

            $end = $start->copy()->addMinutes($len)->seconds(0);
        }

        if ($start) {
            $start = $start->timezone($tz);
        }
        if ($end) {
            $end = $end->timezone($tz);
        }

        return [$start, $end];
    }

    protected static function consultationFeeAmountForDoctor(?int $doctorId): float
    {
        $doctorId = (int) ($doctorId ?? 0);
        if ($doctorId <= 0) {
            return 0.0;
        }

        $fee = (float) \App\Models\Doctor::query()
            ->whereKey($doctorId)
            ->value('consultation_fee');

        return max(0, round($fee, 3));
    }

    protected static function ensureVisitForBooking(\App\Models\Booking $r, \Carbon\Carbon $now): \App\Models\Visit
    {
        $visit = \App\Models\Visit::firstOrNew(['booking_id' => $r->id]);

        $visit->fill([
            'patient_id' => $r->patient_id,
            'doctor_id' => $r->doctor_id,
            'branch_id' => $r->branch_id,
            'restaurant_table_id' => $r->table_id,
            'source' => $r->source,
            'booking_code' => $r->booking_code,
        ]);

        // Keep legacy: do NOT start service here
        $visit->checked_in_at = $visit->checked_in_at ?? $now;
        $visit->queued_at = $visit->queued_at ?? $now;

        // Do not override completed/no_show/cancelled
        if (! in_array(($visit->status ?? null), ['completed', 'no_show', 'cancelled'], true)) {
            $visit->status = $visit->status ?: \App\Models\Visit::STATUS_CREATED;
        }

        $visit->save();

        return $visit;
    }

    protected static function isConsultationPaidForBooking(Booking $r): bool
    {
        // No visit yet -> not paid
        $visitId = Visit::query()->where('booking_id', $r->id)->value('id');
        if (! $visitId) {
            return false;
        }

        return \App\Models\VisitPayment::query()
            ->where('visit_id', (int) $visitId)
            ->where('kind', \App\Models\VisitPayment::KIND_CONSULTATION)
            ->where('status', 'paid')
            ->where('amount', '>', 0)
            ->exists();
    }

    protected static function consultationLabel(): string
    {
        return 'Consultation Fee';
    }

    private static function isTerminal(Booking $r): bool
    {
        return in_array($r->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    private static function isCheckedIn(Booking $r): bool
    {
        return ! is_null($r->checked_in_at);
    }

    private static function hasCoreIds(Booking $r): bool
    {
        return (int) $r->patient_id > 0 && (int) $r->doctor_id > 0 && (int) $r->branch_id > 0;
    }

    private static function visitIdForBooking(Booking $r): ?int
    {
        $id = Visit::query()->where('booking_id', $r->id)->value('id');

        return $id ? (int) $id : null;
    }

    private static function hasVisit(Booking $r): bool
    {
        return self::visitIdForBooking($r) !== null;
    }

    private static function visitIsOpen(Booking $r): bool
    {
        $visit = Visit::query()->select('status', 'completed_at')->where('booking_id', $r->id)->first();
        if (! $visit) {
            return false;
        }

        $status = (string) ($visit->status ?? '');

        return ! in_array($status, ['completed', 'no_show', 'cancelled'], true) && is_null($visit->completed_at);
    }

    private static function hasConsultationCharge(Booking $r): bool
    {
        $visitId = self::visitIdForBooking($r);
        if (! $visitId) {
            return false;
        }

        return \App\Models\VisitCharge::query()
            ->where('visit_id', $visitId)
            ->where('label', self::consultationLabel())
            ->exists();
    }

    private static function hasConsultationPaid(Booking $r): bool
    {
        return self::isConsultationPaidForBooking($r); // keep your existing logic
    }

    private static function visitBalanceForBooking(Booking $r): ?float
    {
        $visit = Visit::query()
            ->where('booking_id', $r->id)
            ->withSum(['payments as paid_sum' => function ($q) {
                $q->where('status', 'paid');
            }], 'amount')
            ->first(['id', 'fees_total', 'discount_total']);

        if (! $visit) {
            return null;
        }

        $total = (float) ($visit->fees_total ?? 0.0); // snapshot total
        $paid = (float) ($visit->paid_sum ?? 0.0);

        return $total - $paid;
    }

    private static function visitIsFullyPaidForBooking(Booking $r, float $tolerance = 0.005): bool
    {
        $balance = self::visitBalanceForBooking($r);

        // If we can’t determine, be conservative: hide discharge
        if ($balance === null) {
            return false;
        }

        return $balance <= $tolerance;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
