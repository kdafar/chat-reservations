<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoctorResource\Pages;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\RestaurantTable;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class DoctorResource extends Resource
{
    protected static ?string $model = Doctor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Clinic — Setup';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Professional Profile')
                ->schema([
                    Forms\Components\FileUpload::make('avatar_path')
                        ->label('Profile Photo')
                        ->avatar()
                        ->directory('doctors-avatars')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Doctor Name')
                        ->required()
                        ->placeholder('Dr. John Doe'),

                    Forms\Components\TextInput::make('specialty')
                        ->label('Specialty')
                        ->required()
                        ->datalist([
                            'General Practice', 'Cardiology', 'Dermatology',
                            'Pediatrics', 'Dentistry', 'Orthopedics',
                        ]),

                    Forms\Components\TextInput::make('license_number')
                        ->label('Medical License #'),

                    Forms\Components\TextInput::make('consultation_fee')
                        ->label('Consultation Fee')
                        ->numeric()
                        ->prefix('KWD')
                        ->required()                 // disallow null
                        ->default(1)                 // optional: avoids 0/null on first load
                        ->minValue(0.001)            // disallow 0.00
                        ->rule('gt:0')               // extra server-side guard (greater than 0)
                        ->helperText('Must be greater than 0.'),
                ])->columns(2),

            Forms\Components\Section::make('Assignment')
                ->description('Where does this doctor work?')
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label('Clinic (Partner)')
                        ->options(Partner::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('branch_id', null) || $set('restaurant_table_id', null))
                        ->required(),

                    Forms\Components\Select::make('branch_id')
                        ->label('Primary Branch')
                        ->options(function (Forms\Get $get) {
                            $partnerId = $get('partner_id');
                            if (! $partnerId) {
                                return [];
                            }

                            return Branch::query()
                                ->where('partner_id', $partnerId)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('restaurant_table_id', null); // IMPORTANT: reset room on branch change
                        })
                        ->required(),

                    Forms\Components\Select::make('restaurant_table_id')
                        ->label('Room')
                        ->helperText('Optional. Room must belong to the selected branch. A room can be assigned to only one doctor.')
                        ->options(function (Forms\Get $get, ?Doctor $record) {
                            $branchId = $get('branch_id');

                            if (! $branchId) {
                                return [];
                            }

                            return RestaurantTable::query()
                                ->where('branch_id', $branchId)
                                // optional: only show active/available rooms if you want
                                // ->where('status', 'available')
                                ->where(function ($q) use ($record) {
                                    // show unassigned rooms OR keep current doctor's room visible in edit
                                    $q->whereDoesntHave('doctor')
                                        ->orWhereHas('doctor', fn ($qq) => $record?->id ? $qq->where('id', $record->id) : $qq->whereRaw('1=0'));
                                })
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Forms\Get $get) => blank($get('branch_id')))
                        ->rules(function (Forms\Get $get, ?Doctor $record) {
                            $branchId = $get('branch_id');

                            return [
                                'nullable',
                                Rule::exists('restaurant_tables', 'id')
                                    ->when($branchId, fn ($rule) => $rule->where('branch_id', $branchId)),
                                Rule::unique('doctors', 'restaurant_table_id')
                                    ->ignore($record?->id),
                            ];
                        }),
                    // NEW (add-only): Link doctor to an existing auth user
                    Forms\Components\Select::make('user_id')
                        ->label('Linked User (Login)')
                        ->helperText('Optional. Link this doctor to a system user for permissions and doctor login.')
                        ->options(fn () => User::query()
                            // show users not already linked to another doctor
                            ->whereDoesntHave('doctorProfile')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Work Schedule')
                ->description('Define the weekly shifts for this doctor.')
                ->schema([
                    Forms\Components\Repeater::make('working_hours')
                        ->label('Weekly Slots')
                        ->helperText('Tip: Create one day, then click the "Clone" (duplicate) button to quickly copy it to other days.')
                        ->schema([
                            Forms\Components\Select::make('day')
                                ->options([
                                    1 => 'Monday',
                                    2 => 'Tuesday',
                                    3 => 'Wednesday',
                                    4 => 'Thursday',
                                    5 => 'Friday',
                                    6 => 'Saturday',
                                    0 => 'Sunday',
                                ])
                                ->required()
                                ->columnSpan(1),

                            Forms\Components\Group::make([
                                Forms\Components\TimePicker::make('start')
                                    ->label('Start Time')
                                    ->seconds(false)
                                    ->required(),

                                Forms\Components\TimePicker::make('end')
                                    ->label('End Time')
                                    ->seconds(false)
                                    ->required(),
                            ])->columns(2)->columnSpan(2),
                        ])
                        ->columns(3)
                        ->defaultItems(1)
                        ->grid(1)
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => isset($state['day'])
                            ? (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$state['day'] ?? 0]." ({$state['start']} - {$state['end']})")
                            : null
                        ),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Doctor is Available for Booking')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_path')
                    ->circular()
                    ->label(''),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Doctor $record) => $record->specialty),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable(),

                Tables\Columns\TextColumn::make('room.name')
                    ->label('Room')
                    ->placeholder('-')
                    ->toggleable(),

                // NEW (add-only): show linked user
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('-')
                    ->formatStateUsing(function ($state, Doctor $record) {
                        $u = $record->user;
                        if (! $u) {
                            return '-';
                        }

                        // keep it compact
                        return trim(($u->name ?? '').($u->email ? " ({$u->email})" : ''));
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('working_hours')
                    ->label('Shifts')
                    ->formatStateUsing(function ($state, \App\Models\Doctor $record) {
                        $value = $record->working_hours;

                        if (is_string($value)) {
                            $decoded = json_decode($value, true);
                            $value = is_array($decoded) ? $decoded : null;
                        }

                        if (! is_array($value) || empty($value)) {
                            return '-';
                        }

                        return count($value).' days';
                    })
                    ->tooltip(function (\App\Models\Doctor $record): ?string {
                        $value = $record->working_hours;

                        if (is_string($value)) {
                            $decoded = json_decode($value, true);
                            $value = is_array($decoded) ? $decoded : null;
                        }

                        if (! is_array($value) || empty($value)) {
                            return null;
                        }

                        $dayNames = [
                            0 => 'Sun',
                            1 => 'Mon',
                            2 => 'Tue',
                            3 => 'Wed',
                            4 => 'Thu',
                            5 => 'Fri',
                            6 => 'Sat',
                        ];

                        $lines = [];

                        foreach ($value as $row) {
                            $day = $dayNames[(int) ($row['day'] ?? 0)] ?? 'Day';
                            $start = (string) ($row['start'] ?? '');
                            $end = (string) ($row['end'] ?? '');

                            if ($start === '' || $end === '') {
                                continue;
                            }

                            $lines[] = "{$day}: {$start} - {$end}";
                        }

                        if (empty($lines)) {
                            return null;
                        }

                        // Filament tooltips accept plain text; newlines are fine.
                        return implode("\n", $lines);
                    })
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('consultation_fee')
                    ->label('Fee')
                    ->prefix('KWD ')
                    ->sortable()
                    ->alignRight()
                    ->placeholder('-')
                    ->formatStateUsing(function ($state) {
                        $v = (float) ($state ?? 0);

                        return $v > 0 ? number_format($v, 2) : '-';
                    }),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name'),
            ])
            ->actions([
                // NEW (add-only): quick link/unlink from table without editing
                Tables\Actions\Action::make('linkUser')
                    ->label('Link to User')
                    ->icon('heroicon-o-link')
                    ->modalHeading('Link Doctor to a User')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->options(fn (Doctor $record) => User::query()
                                // allow keeping current linked user in options
                                ->where(function ($q) use ($record) {
                                    $q->whereDoesntHave('doctorProfile')
                                        ->orWhere('id', $record->user_id);
                                })
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->action(function (Doctor $record, array $data): void {
                        $record->update([
                            'user_id' => $data['user_id'] ?? null,
                        ]);
                    }),

                Tables\Actions\Action::make('unlinkUser')
                    ->label('Unlink User')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (Doctor $record) => (bool) $record->user_id)
                    ->requiresConfirmation()
                    ->action(fn (Doctor $record) => $record->update(['user_id' => null])),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\DoctorResource\RelationManagers\DoctorShiftsRelationManager::class,
            \App\Filament\Resources\DoctorResource\RelationManagers\CompensationProfileRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDoctors::route('/'),
            'create' => Pages\CreateDoctor::route('/create'),
            'edit' => Pages\EditDoctor::route('/{record}/edit'),
        ];
    }
}
