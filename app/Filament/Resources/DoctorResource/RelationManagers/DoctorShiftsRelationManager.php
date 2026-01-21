<?php

namespace App\Filament\Resources\DoctorResource\RelationManagers;

use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DoctorShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'shifts';

    protected static ?string $recordTitleAttribute = 'shift_date';

    protected static ?string $title = 'Daily Shifts (Overrides)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('shift_date')
                ->required(),

            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->options(fn () => Branch::query()->pluck('name', 'id')->all())
                ->required(),

            Forms\Components\TimePicker::make('start_time')
                ->seconds(false)
                ->required(),

            Forms\Components\TimePicker::make('end_time')
                ->seconds(false)
                ->required(),

            Forms\Components\TextInput::make('break_minutes')
                ->numeric()
                ->default(0)
                ->minValue(0),

            Forms\Components\Toggle::make('is_cancelled')
                ->label('Cancelled'),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shift_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch'),

                Tables\Columns\TextColumn::make('start_time')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('end_time')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('break_minutes')
                    ->label('Break (min)')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_cancelled')
                    ->boolean()
                    ->label('Cancelled'),
            ])
            ->defaultSort('shift_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),

                Tables\Actions\Action::make('createWeek')
                    ->label('Create Weekly Shifts')
                    ->icon('heroicon-o-calendar-days')
                    ->modalHeading('Create Weekly Shifts')
                    ->form([
                        Forms\Components\DatePicker::make('week_start')
                            ->label('Week Start Date')
                            ->helperText('Any date in the week (e.g. Monday)')
                            ->required(),

                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn () => Branch::query()->pluck('name', 'id')->all())
                            ->required(),

                        Forms\Components\TimePicker::make('start_time')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\TimePicker::make('end_time')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\TextInput::make('break_minutes')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\CheckboxList::make('days')
                            ->label('Days of Week')
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                            ])
                            ->columns(4)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $doctor = $this->getOwnerRecord();

                        $weekStart = \Carbon\Carbon::parse($data['week_start'])->startOfWeek();

                        foreach ($data['days'] as $day) {
                            $shiftDate = $weekStart->copy()->addDays((int) $day);

                            $doctor->shifts()->create([
                                'shift_date' => $shiftDate->toDateString(),
                                'branch_id' => $data['branch_id'],
                                'start_time' => $data['start_time'],
                                'end_time' => $data['end_time'],
                                'break_minutes' => $data['break_minutes'] ?? 0,
                                'is_cancelled' => false,
                            ]);
                        }
                    })
                    ->requiresConfirmation(),
            ])

            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
