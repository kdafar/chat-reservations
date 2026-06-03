<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffLeaveResource\Pages;
use App\Models\StaffLeave;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffLeaveResource extends Resource
{
    protected static ?string $model = StaffLeave::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.hr') ?: 'HR';
    }

    /**
     * Anyone authenticated can use the leaves page — but data they see
     * is scoped in getEloquentQuery() to either ALL (admins) or just
     * their own user_id (regular staff). Approve/reject actions are
     * additionally hidden for non-admins.
     */
    public static function canAccess(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->can('view_any_staff_leaves'));
    }

    /**
     * HR managers see all leaves and can approve/reject. "Manager" is
     * defined by the `delete_any_staff_leaves` permission (only seeded
     * to clinic_admin + branch_manager + admin).
     */
    public static function isHrManager(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->can('delete_any_staff_leaves'));
    }

    public static function canDelete($record): bool
    {
        if (self::isHrManager()) {
            return true;
        }
        // Regular staff can only delete THEIR OWN PENDING leaves (cancel before approval).
        return $record->user_id === auth()->id()
            && $record->status === StaffLeave::STATUS_PENDING
            && auth()->user()?->can('delete_staff_leaves');
    }

    public static function canEdit($record): bool
    {
        if (self::isHrManager()) {
            return true;
        }
        return $record->user_id === auth()->id()
            && $record->status === StaffLeave::STATUS_PENDING
            && auth()->user()?->can('update_staff_leaves');
    }

    public static function getNavigationLabel(): string
    {
        return 'Leaves';
    }

    public static function getModelLabel(): string
    {
        return 'Leave';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Staff Leaves';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Staff member')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        // Non-HR-managers can only request for themselves;
                        // the field is preset + disabled so they can't pick
                        // a coworker.
                        ->default(fn () => self::isHrManager() ? null : auth()->id())
                        ->disabled(fn () => ! self::isHrManager())
                        ->dehydrated(),

                    Forms\Components\Select::make('type')
                        ->options(self::typeOptions())
                        ->default(StaffLeave::TYPE_ANNUAL)
                        ->required(),

                    Forms\Components\DatePicker::make('starts_on')
                        ->required()->native(false)->default(now()),

                    Forms\Components\DatePicker::make('ends_on')
                        ->required()->native(false)->default(now())
                        ->afterOrEqual('starts_on'),

                    Forms\Components\Textarea::make('reason')
                        ->rows(2)->maxLength(500)->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Decision')
                ->columns(2)
                ->visible(fn ($record) => $record !== null && self::isHrManager())
                ->schema([
                    Forms\Components\Select::make('status')->options(self::statusOptions())->required(),
                    Forms\Components\DateTimePicker::make('decided_at')->seconds(false),
                    Forms\Components\Textarea::make('decision_notes')->rows(2)->maxLength(1000)->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn (string $s) => ucfirst($s)),
                Tables\Columns\TextColumn::make('starts_on')->label('From')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('ends_on')->label('To')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('days_count')->label('Days')->numeric(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'cancelled' => 'gray',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('decidedBy.name')->label('Decided by')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Requested')->dateTime('Y-m-d H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('type')->options(self::typeOptions()),
                Tables\Filters\SelectFilter::make('user_id')->label('Staff')->relationship('user', 'name')->searchable(),
                Tables\Filters\TernaryFilter::make('doctor_id')->label('Only doctors')->placeholder('All staff')->trueLabel('Doctors only')->falseLabel('Non-doctors only')->queries(
                    true: fn (Builder $q) => $q->whereNotNull('doctor_id'),
                    false: fn (Builder $q) => $q->whereNull('doctor_id'),
                    blank: fn (Builder $q) => $q,
                ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                \App\Filament\Exports\ExcelExportActions::header(),
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['requested_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (StaffLeave $r) => $r->status === StaffLeave::STATUS_PENDING && self::isHrManager())
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('decision_notes')->rows(2)->maxLength(1000)])
                    ->action(function (StaffLeave $r, array $data) {
                        $r->forceFill([
                            'status' => StaffLeave::STATUS_APPROVED,
                            'decision_notes' => $data['decision_notes'] ?? null,
                            'decided_at' => now(),
                            'decided_by_user_id' => auth()->id(),
                        ])->save();
                        Notification::make()->title('Leave approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (StaffLeave $r) => $r->status === StaffLeave::STATUS_PENDING && self::isHrManager())
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('decision_notes')->rows(2)->maxLength(1000)->required()])
                    ->action(function (StaffLeave $r, array $data) {
                        $r->forceFill([
                            'status' => StaffLeave::STATUS_REJECTED,
                            'decision_notes' => $data['decision_notes'],
                            'decided_at' => now(),
                            'decided_by_user_id' => auth()->id(),
                        ])->save();
                        Notification::make()->title('Leave rejected')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ])
            ->emptyStateHeading('No leave requests yet')
            ->emptyStateDescription('Track staff time-off here. Works for any worker — doctors, reception, lab techs, admins.')
            ->emptyStateIcon('heroicon-o-calendar');
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()
            ->with(['user', 'doctor', 'decidedBy'])
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class]);

        // Data scoping: non-HR-managers only see their own leaves.
        if (! self::isHrManager()) {
            $q->where('user_id', auth()->id());
        }

        return $q;
    }

    /** @return array<string, string> */
    protected static function statusOptions(): array
    {
        return [
            StaffLeave::STATUS_PENDING => 'Pending',
            StaffLeave::STATUS_APPROVED => 'Approved',
            StaffLeave::STATUS_REJECTED => 'Rejected',
            StaffLeave::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /** @return array<string, string> */
    protected static function typeOptions(): array
    {
        return [
            StaffLeave::TYPE_ANNUAL => 'Annual',
            StaffLeave::TYPE_SICK => 'Sick',
            StaffLeave::TYPE_MATERNITY => 'Maternity',
            StaffLeave::TYPE_UNPAID => 'Unpaid',
            StaffLeave::TYPE_EMERGENCY => 'Emergency',
            StaffLeave::TYPE_OTHER => 'Other',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffLeaves::route('/'),
            'create' => Pages\CreateStaffLeave::route('/create'),
            'edit' => Pages\EditStaffLeave::route('/{record}/edit'),
        ];
    }
}
