<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffAttendanceResource\Pages;
use App\Models\StaffAttendance;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffAttendanceResource extends Resource
{
    protected static ?string $model = StaffAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 31;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.hr') ?: 'HR';
    }

    public static function canAccess(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->can('view_any_staff_attendances'));
    }

    /**
     * HR managers see all attendance and can edit anyone. Defined by the
     * `delete_any_staff_attendances` permission (clinic_admin + branch_manager
     * + admin only per the seeder).
     */
    public static function isHrManager(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->can('delete_any_staff_attendances'));
    }

    public static function canDelete($record): bool
    {
        return self::isHrManager();
    }

    public static function canEdit($record): bool
    {
        if (self::isHrManager()) {
            return true;
        }
        return $record->user_id === auth()->id()
            && auth()->user()?->can('update_staff_attendances');
    }

    public static function getNavigationLabel(): string
    {
        return 'Attendance';
    }

    public static function getModelLabel(): string
    {
        return 'Attendance entry';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Staff Attendance';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Attendance')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Staff member')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default(fn () => self::isHrManager() ? null : auth()->id())
                        ->disabled(fn () => ! self::isHrManager())
                        ->dehydrated(),

                    Forms\Components\DatePicker::make('work_date')
                        ->required()->native(false)->default(now()),

                    Forms\Components\DateTimePicker::make('clock_in_at')
                        ->seconds(false)->default(now()),

                    Forms\Components\DateTimePicker::make('clock_out_at')
                        ->seconds(false)->afterOrEqual('clock_in_at'),

                    Forms\Components\TextInput::make('hours_worked')
                        ->numeric()->step(0.01)->disabled()->dehydrated(false)
                        ->helperText('Computed automatically from clock-in/out.'),

                    Forms\Components\Textarea::make('notes')
                        ->rows(2)->maxLength(500)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')->label('Staff')->searchable(),
                Tables\Columns\TextColumn::make('doctor.name')->label('Doctor profile')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('work_date')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('clock_in_at')->label('In')->dateTime('H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('clock_out_at')->label('Out')->dateTime('H:i')->placeholder('— (on shift)'),
                Tables\Columns\TextColumn::make('hours_worked')->label('Hours')->numeric(2)->sortable(),
                Tables\Columns\TextColumn::make('recordedBy.name')->label('Recorded by')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')->label('Staff')->relationship('user', 'name')->searchable(),
                Tables\Filters\TernaryFilter::make('doctor_id')->label('Only doctors')->placeholder('All staff')->trueLabel('Doctors only')->falseLabel('Non-doctors only')->queries(
                    true: fn (Builder $q) => $q->whereNotNull('doctor_id'),
                    false: fn (Builder $q) => $q->whereNull('doctor_id'),
                    blank: fn (Builder $q) => $q,
                ),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('work_date', '>=', $d))
                        ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('work_date', '<=', $d))),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                \App\Filament\Exports\ExcelExportActions::header(),
                Tables\Actions\Action::make('clockInSelf')
                    ->label('Clock me in')
                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->color('primary')
                    ->visible(fn () => (bool) auth()->id())
                    ->action(function () {
                        $userId = (int) auth()->id();
                        $today = now()->toDateString();
                        $existing = StaffAttendance::withTrashed()
                            ->where('user_id', $userId)
                            ->where('work_date', $today)
                            ->first();
                        if ($existing) {
                            Notification::make()
                                ->title($existing->trashed()
                                    ? "Today's attendance is in trash — restore it instead."
                                    : 'Already clocked in today.')
                                ->warning()->send();
                            return;
                        }
                        try {
                            StaffAttendance::create([
                                'user_id' => $userId,
                                'work_date' => $today,
                                'clock_in_at' => now(),
                                'recorded_by_user_id' => $userId,
                            ]);
                            Notification::make()->title('Clocked in')->success()->send();
                        } catch (\Illuminate\Database\QueryException $e) {
                            if (str_contains($e->getMessage(), 'staff_attendance_user_date_unique')
                                || str_contains($e->getMessage(), 'Duplicate entry')) {
                                Notification::make()->title('Already clocked in today.')->warning()->send();
                                return;
                            }
                            throw $e;
                        }
                    }),

                Tables\Actions\CreateAction::make()
                    ->label('Add entry')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['recorded_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('clockOut')
                    ->label('Clock out')->icon('heroicon-o-arrow-right-on-rectangle')->color('success')
                    ->visible(fn (StaffAttendance $r) => $r->clock_in_at && ! $r->clock_out_at)
                    ->requiresConfirmation()
                    ->action(function (StaffAttendance $r) {
                        $r->clock_out_at = now();
                        $r->save();
                        Notification::make()
                            ->title('Clocked out')
                            ->body("Worked {$r->hours_worked} hours today.")
                            ->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ])
            ->emptyStateHeading('No attendance recorded yet')
            ->emptyStateDescription('Clock-in / clock-out entries for any staff member appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()
            ->with(['user', 'doctor', 'recordedBy'])
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class]);

        if (! self::isHrManager()) {
            $q->where('user_id', auth()->id());
        }

        return $q;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffAttendances::route('/'),
            'create' => Pages\CreateStaffAttendance::route('/create'),
            'edit' => Pages\EditStaffAttendance::route('/{record}/edit'),
        ];
    }
}
