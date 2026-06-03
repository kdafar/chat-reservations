<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoctorResource\Pages;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\RestaurantTable;
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

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.doctor.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.doctor.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.doctor.label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('clinic_doctor.sections.professional_profile'))
                ->schema([
                    Forms\Components\FileUpload::make('avatar_path')
                        ->label(__('clinic_doctor.fields.avatar.label'))
                        ->avatar()
                        ->directory('doctors-avatars')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label(__('clinic_doctor.fields.name.label'))
                        ->required()
                        ->placeholder(__('clinic_doctor.fields.name.placeholder')),

                    Forms\Components\TextInput::make('specialty')
                        ->label(__('clinic_doctor.fields.specialty.label'))
                        ->required()
                        ->datalist([
                            __('clinic_doctor.options.specialty_suggestions.general_practice'),
                            __('clinic_doctor.options.specialty_suggestions.cardiology'),
                            __('clinic_doctor.options.specialty_suggestions.dermatology'),
                            __('clinic_doctor.options.specialty_suggestions.pediatrics'),
                            __('clinic_doctor.options.specialty_suggestions.dentistry'),
                            __('clinic_doctor.options.specialty_suggestions.orthopedics'),
                        ]),

                    Forms\Components\TextInput::make('license_number')
                        ->label(__('clinic_doctor.fields.license_number.label')),

                    Forms\Components\TextInput::make('email')
                        ->label(__('clinic_doctor.fields.email.label'))
                        ->email()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->disabled(fn (string $operation) => $operation !== 'create')
                        ->dehydrated(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'create'
                            ? __('clinic_doctor.fields.email.helper')
                            : __('clinic_doctor.fields.email.locked_helper'))
                        ->rules(fn (?Doctor $record) => [
                            Rule::unique('users', 'email')
                                ->ignore($record?->user_id),
                        ]),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('clinic_doctor.fields.phone.label'))
                        ->tel(),

                    Forms\Components\TextInput::make('consultation_fee')
                        ->label(__('clinic_doctor.fields.consultation_fee.label'))
                        ->numeric()
                        ->prefix('KWD')
                        ->step('0.001')
                        ->required()
                        ->default(1)
                        ->minValue(0.001)
                        ->rule('gt:0')
                        ->helperText(__('clinic_doctor.fields.consultation_fee.helper')),
                ])->columns(2),

            Forms\Components\Section::make(__('clinic_doctor.sections.assignment'))
                ->description(__('clinic_doctor.sections.assignment_description'))
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label(__('clinic_doctor.fields.partner.label'))
                        ->options(Partner::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('branch_id', null) || $set('restaurant_table_id', null))
                        ->required(),

                    Forms\Components\Select::make('branch_id')
                        ->label(__('clinic_doctor.fields.primary_branch.label'))
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
                        ->label(__('clinic_doctor.fields.room.label'))
                        ->helperText(__('clinic_doctor.fields.room.helper'))
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
                    Forms\Components\Placeholder::make('linked_user_info')
                        ->label(__('clinic_doctor.fields.linked_user.label'))
                        ->content(fn (?Doctor $record) => $record?->user
                            ? $record->user->name.' ('.$record->user->email.')'
                            : __('clinic_doctor.fields.linked_user.auto_create_note'))
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make(__('clinic_doctor.sections.work_schedule'))
                ->description(__('clinic_doctor.sections.work_schedule_description'))
                ->schema([
                    Forms\Components\Repeater::make('working_hours')
                        ->label(__('clinic_doctor.fields.weekly_slots.label'))
                        ->helperText(__('clinic_doctor.fields.weekly_slots.helper'))
                        ->schema([
                            Forms\Components\Select::make('day')
                                ->options([
                                    1 => __('clinic_doctor.options.days.monday'),
                                    2 => __('clinic_doctor.options.days.tuesday'),
                                    3 => __('clinic_doctor.options.days.wednesday'),
                                    4 => __('clinic_doctor.options.days.thursday'),
                                    5 => __('clinic_doctor.options.days.friday'),
                                    6 => __('clinic_doctor.options.days.saturday'),
                                    0 => __('clinic_doctor.options.days.sunday'),
                                ])
                                ->required()
                                ->columnSpan(1),

                            Forms\Components\Group::make([
                                Forms\Components\TimePicker::make('start')
                                    ->label(__('clinic_doctor.fields.start_time.label'))
                                    ->seconds(false)
                                    ->required(),

                                Forms\Components\TimePicker::make('end')
                                    ->label(__('clinic_doctor.fields.end_time.label'))
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
                        ->label(__('clinic_doctor.fields.is_active.label'))
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
                    ->label(__('clinic_doctor.columns.branch'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('room.name')
                    ->label(__('clinic_doctor.columns.room'))
                    ->placeholder('-')
                    ->toggleable(),

                // NEW (add-only): show linked user
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('clinic_doctor.columns.user'))
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
                    ->label(__('clinic_doctor.columns.shifts'))
                    ->formatStateUsing(function ($state, \App\Models\Doctor $record) {
                        $value = $record->working_hours;

                        if (is_string($value)) {
                            $decoded = json_decode($value, true);
                            $value = is_array($decoded) ? $decoded : null;
                        }

                        if (! is_array($value) || empty($value)) {
                            return '-';
                        }

                        return count($value).' '.__('clinic_doctor.columns.days_suffix');
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
                    ->label(__('clinic_doctor.columns.active')),

                Tables\Columns\TextColumn::make('consultation_fee')
                    ->label(__('clinic_doctor.columns.fee'))
                    ->prefix('KWD ')
                    ->sortable()
                    ->alignRight()
                    ->placeholder('-')
                    ->formatStateUsing(function ($state) {
                        $v = (float) ($state ?? 0);

                        return $v > 0 ? number_format($v, 3) : '-';
                    }),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('clinic_doctor.filters.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                \App\Filament\Imports\ExcelImportAction::make()
                    ->importer(\App\Filament\Imports\DoctorImporter::class)
                    ->label('Import'),
                \App\Filament\Exports\ExcelExportActions::header(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(__('clinic_doctor.actions.delete.modal_heading'))
                    ->modalDescription(fn (Doctor $record) => $record->user_id
                        ? __('clinic_doctor.actions.delete.modal_description_with_user', [
                            'email' => optional($record->user)->email ?: '-',
                        ])
                        : __('clinic_doctor.actions.delete.modal_description'))
                    ->modalSubmitActionLabel(__('clinic_doctor.actions.delete.submit')),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false),
            ])
            ->bulkActions([
                \App\Filament\Exports\ExcelExportActions::bulk(),
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false),
            ])
            ->emptyStateHeading(__('resources.doctor.empty_heading'))
            ->emptyStateDescription(__('resources.doctor.empty_description'))
            ->emptyStateIcon('heroicon-o-academic-cap');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\DoctorResource\RelationManagers\LeavesRelationManager::class,
            \App\Filament\Resources\DoctorResource\RelationManagers\AttendanceRelationManager::class,
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
