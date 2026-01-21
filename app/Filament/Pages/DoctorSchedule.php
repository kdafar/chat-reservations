<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\RestaurantTable; // Added per patch
use App\Models\Visit;
// Added per patch
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // Added per patch

class DoctorSchedule extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?string $title = 'Doctor Schedule';

    protected static ?int $navigationSort = 40;

    protected static string $view = 'filament.pages.doctor-schedule';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_doctor-schedule');
    }

    /**
     * Enable debug via .env:
     * DOCTOR_SCHEDULE_FILTER_DEBUG=true
     */
    protected function filterDebugEnabled(): bool
    {
        return (bool) config('doctor_schedule.filter_debug', (bool) env('DOCTOR_SCHEDULE_FILTER_DEBUG', true));
    }

    public function mount(): void
    {
        if (! $this->filterDebugEnabled()) {
            return;
        }

        DB::listen(function ($query) {
            Log::debug('[DoctorSchedule SQL]', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        });
    }

    /**
     * MANUAL OVERRIDE: Apply filters directly to the query builder.
     * This bypasses potential Filament closure scope issues.
     */
    protected function applyScheduleFiltersToQuery(Builder $query, string $tz): Builder
    {
        // Doctor filter (IMPORTANT: was missing in your manual apply)
        $doctorFilter = $this->getTableFilterState('doctor_id') ?? null;
        $doctorId = is_array($doctorFilter) ? ($doctorFilter['value'] ?? null) : $doctorFilter;
        if (filled($doctorId)) {
            $query->where('doctor_id', (int) $doctorId);
        }

        // Period preset
        $when = $this->getTableFilterState('when') ?? [];
        $preset = $when['preset'] ?? null;

        if ($preset === 'today') {
            $query->whereDate('res_date', Carbon::now($tz)->toDateString());
        } elseif ($preset === 'tomorrow') {
            $query->whereDate('res_date', Carbon::now($tz)->addDay()->toDateString());
        } elseif ($preset === 'week') {
            $query->whereBetween('res_date', [
                Carbon::now($tz)->startOfWeek()->toDateString(),
                Carbon::now($tz)->endOfWeek()->toDateString(),
            ]);
        } elseif ($preset === 'all') {
            $query->whereDate('res_date', '>=', Carbon::now($tz)->toDateString());
        }

        // Time of day
        $tod = $this->getTableFilterState('time_of_day') ?? [];
        $slot = $tod['slot'] ?? null;

        if ($slot === 'morning') {
            $query->whereTime('res_time', '>=', '08:00:00')
                ->whereTime('res_time', '<', '12:00:00');
        } elseif ($slot === 'afternoon') {
            $query->whereTime('res_time', '>=', '12:00:00')
                ->whereTime('res_time', '<', '17:00:00');
        } elseif ($slot === 'evening') {
            $query->whereTime('res_time', '>=', '17:00:00')
                ->whereTime('res_time', '<', '23:00:00');
        }

        return $query;
    }

    protected function debugFinalTableQuery(Builder $query): void
    {
        if (! $this->filterDebugEnabled()) {
            return;
        }
    }

    /**
     * Resolve patient_id for booking in a conservative, backward-compatible way.
     * - Prefer booking.patient_id if present.
     * - Fall back to meta patient_id if used historically.
     * - Fall back to Patient.phone match on msisdn.
     */
    protected function resolvePatientIdForBooking(Booking $record): ?int
    {
        // Preferred new column
        if (isset($record->patient_id) && $record->patient_id) {
            return (int) $record->patient_id;
        }

        // Backward compat: meta payloads
        if (isset($record->meta) && is_array($record->meta) && isset($record->meta['patient_id']) && $record->meta['patient_id']) {
            return (int) $record->meta['patient_id'];
        }

        // Backward compat: phone match
        if (! empty($record->msisdn)) {
            $phone = preg_replace('/\D+/', '', (string) $record->msisdn);
            if ($phone !== '') {
                $p = Patient::query()->where('phone', $phone)->first();
                if ($p) {
                    return (int) $p->id;
                }
            }
        }

        return null;
    }

    public function table(Table $table): Table
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        return $table
            ->query(
                Booking::query()
                    // Default to showing active, but filters can further narrow results.
                    ->whereIn('status', ['confirmed', 'pending'])
            )
            ->modifyQueryUsing(function (Builder $query) use ($tz) {
                $this->applyScheduleFiltersToQuery($query, $tz);
                $this->debugFinalTableQuery($query);
            })
            ->striped()
            ->persistFiltersInSession()
            ->filtersLayout(FiltersLayout::AboveContent)
            ->defaultSort('res_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('res_time')
                    ->label('Time')
                    ->time('h:i A')
                    ->sortable()
                    ->weight('bold')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('res_date')
                    ->label('Date')
                    ->date('D, M d')
                    ->sortable()
                    ->description(fn (Booking $r) => $r->res_date
                        ? ($r->res_date->isPast() && ! $r->res_date->isToday()
                            ? 'Was scheduled '.$r->res_date->diffForHumans()
                            : ($r->res_date->isToday() ? 'Today' : 'in '.$r->res_date->diffForHumans()))
                        : null
                    ),

                Tables\Columns\TextColumn::make('booking_code')
                    ->label('Ref')
                    ->fontFamily('mono')
                    ->color('gray')
                    ->copyable(),

                Tables\Columns\TextColumn::make('doctor.name')
                    ->label('Doctor')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Patient')
                    ->searchable(['meta->name', 'msisdn'])
                    ->description(fn (Booking $record) => $record->msisdn),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'completed' => 'primary',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('qr_token')
                    ->label('QR Pass')
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('heroicon-o-x-mark')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('doctor_id')
                    ->label('Filter by Doctor')
                    ->options(Doctor::query()->where('is_active', true)->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->default(fn () => Doctor::query()->where('is_active', true)->orderBy('id')->value('id')),

                Filter::make('when')
                    ->label('Period')
                    ->form([
                        Forms\Components\ToggleButtons::make('preset')
                            ->options([
                                'today' => 'Today',
                                'tomorrow' => 'Tomorrow',
                                'week' => 'This week',
                                'all' => 'All Upcoming',
                            ])
                            ->inline()
                            ->reactive()
                            ->default('today'),
                    ])
                    ->query(function (Builder $q, array $data) use ($tz) {
                        $preset = $data['preset'] ?? null;

                        if (config('doctor_schedule.filter_debug')) {
                            Log::debug('[DoctorSchedule Filter] when', ['data' => $data]);
                        }

                        return match ($preset) {
                            'today' => $q->whereDate('res_date', Carbon::now($tz)->toDateString()),
                            'tomorrow' => $q->whereDate('res_date', Carbon::now($tz)->addDay()->toDateString()),
                            'week' => $q->whereBetween('res_date', [
                                Carbon::now($tz)->startOfWeek()->toDateString(),
                                Carbon::now($tz)->endOfWeek()->toDateString(),
                            ]),
                            'all' => $q->whereDate('res_date', '>=', Carbon::now($tz)->toDateString()),
                            default => $q,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $preset = $data['preset'] ?? null;

                        return match ($preset) {
                            'today' => 'Period: Today',
                            'tomorrow' => 'Period: Tomorrow',
                            'week' => 'Period: This week',
                            'all' => 'Period: All upcoming',
                            default => null,
                        };
                    }),

                Filter::make('time_of_day')
                    ->label('Time of day')
                    ->form([
                        Forms\Components\ToggleButtons::make('slot')
                            ->options([
                                'morning' => 'Morning (08–12)',
                                'afternoon' => 'Afternoon (12–17)',
                                'evening' => 'Evening (17–23)',
                            ])
                            ->inline()
                            ->reactive(),
                    ])
                    ->query(function (Builder $q, array $data) {
                        $slot = $data['slot'] ?? null;

                        if (config('doctor_schedule.filter_debug')) {
                            Log::debug('[DoctorSchedule Filter] time_of_day', ['data' => $data]);
                        }

                        return match ($slot) {
                            'morning' => $q->whereTime('res_time', '>=', '08:00:00')->whereTime('res_time', '<', '12:00:00'),
                            'afternoon' => $q->whereTime('res_time', '>=', '12:00:00')->whereTime('res_time', '<', '17:00:00'),
                            'evening' => $q->whereTime('res_time', '>=', '17:00:00')->whereTime('res_time', '<', '23:00:00'),
                            default => $q,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['slot'] ?? null) {
                            'morning' => 'Time: Morning',
                            'afternoon' => 'Time: Afternoon',
                            'evening' => 'Time: Evening',
                            default => null,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('open_whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Booking $r) => 'https://wa.me/'.preg_replace('/\D+/', '', (string) $r->msisdn))
                    ->openUrlInNewTab()
                    ->visible(fn (Booking $r) => filled($r->msisdn)),

                Tables\Actions\Action::make('check_in')
                    ->label('Check In')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $r) => $r->status === 'confirmed' && is_null($r->checked_in_at))
                    ->tooltip(function (Booking $r) {
                        $tz = config('app.timezone', 'Asia/Kuwait');
                        $now = Carbon::now($tz);

                        $windowMinutes = max(1, (int) config('clinic.checkin_window_minutes', 60));

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

                        if (! ($now->lt($early) || $now->gt($late))) {
                            return 'Check-in window is open. Allowed within ±'.$windowMinutes.' minutes of the appointment start.';
                        }

                        return
                            'Check-in allowed only within ±'.$windowMinutes." minutes of the appointment start.\n".
                            'Appointment: '.$start->format('Y-m-d h:i A')."\n".
                            'Allowed window: '.$early->format('h:i A').' – '.$late->format('h:i A');
                    })
                    ->action(function (Booking $r) {
                        $tz = config('app.timezone', 'Asia/Kuwait');
                        $now = Carbon::now($tz);

                        // 1) Hard guards
                        if (! $r->patient_id || ! $r->doctor_id) {
                            Notification::make()
                                ->title('Check-in Failed')
                                ->body('Missing Patient or Doctor ID.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // 2) Schedule guard (date + time)
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

                        if (preg_match('/^\d{2}:\d{2}$/', $timePart)) {
                            $timePart .= ':00';
                        }

                        // 3) Resolve appointment start
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

                        // 4) Window rule
                        $windowMinutes = max(1, (int) config('clinic.checkin_window_minutes', 60));
                        $early = $start->copy()->subMinutes($windowMinutes);
                        $late = $start->copy()->addMinutes($windowMinutes);

                        if ($now->lt($early) || $now->gt($late)) {
                            Notification::make()
                                ->title('Check-in Not Allowed')
                                ->body(
                                    'Check-in is allowed only within '.$windowMinutes." minutes before/after the appointment.\n".
                                    'Appointment: '.$start->format('Y-m-d h:i A')."\n".
                                    'Allowed window: '.$early->format('h:i A').' – '.$late->format('h:i A')
                                )
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        // 5) Ensure booking_code
                        if (empty($r->booking_code)) {
                            $r->update(['booking_code' => strtoupper(Str::random(6))]);
                            $r->refresh();
                        }

                        // 6) Atomic: room -> booking -> visit
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

                                    if (($table->status ?? null) !== 'available') {
                                        throw new \RuntimeException("Room {$table->name} is not available.");
                                    }

                                    $table->update(['status' => 'occupied']);
                                }

                                // B) Booking check-in (DO NOT invent a new status; keep enum valid)
                                $r->update([
                                    'checked_in_at' => $now,
                                    'status' => 'confirmed',
                                ]);

                                // C) Visit queue (NO service_started_at here)
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
                                    // keep if you have queued_at column (otherwise remove this line)
                                    'queued_at' => $visit->queued_at ?? $now,
                                ]);

                                // Always queue on check-in (idempotent + consistent)
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

                // Keep cancel/complete if you still want them here, but do NOT rely on a "checked_in" booking status.
            ])
            ->poll('30s');
    }
}
