<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\StaffAttendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Attendance';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('work_date')->native(false)->required()->default(now()),
            Forms\Components\DateTimePicker::make('clock_in_at')->seconds(false)->default(now()),
            Forms\Components\DateTimePicker::make('clock_out_at')->seconds(false)->afterOrEqual('clock_in_at'),
            Forms\Components\Textarea::make('notes')->rows(2)->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('work_date')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('clock_in_at')->label('In')->dateTime('H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('clock_out_at')->label('Out')->dateTime('H:i')->placeholder('— (on shift)'),
                Tables\Columns\TextColumn::make('hours_worked')->label('Hours')->numeric(2),
            ])
            ->headerActions([
                Tables\Actions\Action::make('clockIn')
                    ->label('Clock in now')
                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->color('primary')
                    ->action(function () {
                        $user = $this->getOwnerRecord();
                        $today = now()->toDateString();
                        $existing = StaffAttendance::withTrashed()
                            ->where('user_id', $user->id)
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
                                'user_id' => $user->id,
                                'work_date' => $today,
                                'clock_in_at' => now(),
                                'recorded_by_user_id' => (int) (auth()->id() ?? 0) ?: null,
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
                        $data['user_id'] = $this->getOwnerRecord()->id;
                        $data['recorded_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('clockOut')
                    ->label('Clock out')->icon('heroicon-o-arrow-right-on-rectangle')->color('success')
                    ->visible(fn (StaffAttendance $r) => $r->clock_in_at && ! $r->clock_out_at)
                    ->action(function (StaffAttendance $r) {
                        $r->clock_out_at = now();
                        $r->save();
                        Notification::make()->title('Clocked out')->body("Hours: {$r->hours_worked}")->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
