<?php

namespace App\Filament\Resources\Inpatient;

use App\Filament\Resources\Inpatient\AdmissionResource\Pages;
use App\Filament\Resources\Inpatient\AdmissionResource\RelationManagers;
use App\Models\Inpatient\Admission;
use App\Models\Inpatient\Bed;
use App\Services\Inpatient\AdmissionService;
use App\Services\Inpatient\BedAssignmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdmissionResource extends Resource
{
    protected static ?string $model = Admission::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.inpatient');
    }

    public static function getNavigationBadge(): ?string
    {
        // Show running count of active inpatients in the nav.
        $count = Admission::query()->where('status', Admission::STATUS_ACTIVE)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Patient & Doctor')->columns(2)->schema([
                Forms\Components\Select::make('patient_id')
                    ->relationship('patient', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('admitting_doctor_id')
                    ->relationship('admittingDoctor', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->required()
                    ->preload(),

                Forms\Components\Select::make('admitting_visit_id')
                    ->relationship('admittingVisit', 'id')
                    ->searchable()
                    ->placeholder('— Direct admit —')
                    ->helperText('OPD/ER visit that triggered admission, if any.'),
            ]),

            Forms\Components\Section::make('Clinical')->columns(1)->schema([
                Forms\Components\Textarea::make('admission_reason')->required()->rows(2),
                Forms\Components\Textarea::make('diagnosis')->rows(2),
            ]),

            Forms\Components\Section::make('Timeline')->columns(2)->schema([
                Forms\Components\DateTimePicker::make('admitted_at')
                    ->required()
                    ->default(now())
                    ->seconds(false),
                Forms\Components\DateTimePicker::make('expected_discharge_at')
                    ->seconds(false)
                    ->helperText('Optional planning hint, not enforced.'),
            ]),

            Forms\Components\Section::make('Initial Bed (optional)')->columns(1)
                ->visible(fn ($record) => $record === null) // only on create
                ->schema([
                    Forms\Components\Select::make('_initial_bed_id')
                        ->label('Assign to bed')
                        ->options(function () {
                            return Bed::query()
                                ->with('ward')
                                ->where('status', Bed::STATUS_AVAILABLE)
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Bed $b) => [
                                    $b->id => ($b->ward?->name ?? 'Ward').' / '.$b->code.' ('.($b->ward?->name ?? 'n/a').')',
                                ]);
                        })
                        ->searchable()
                        ->dehydrated(false)
                        ->helperText('Leave blank to admit without a bed (assign later from the admission detail page).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('admitted_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('admission_code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('admittingDoctor.name')->label('Doctor')->toggleable(),
                Tables\Columns\TextColumn::make('currentBedStay.bed.code')
                    ->label('Bed')
                    ->placeholder('— unassigned —'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Admission::STATUS_ACTIVE,
                        'success' => Admission::STATUS_DISCHARGED,
                        'gray' => Admission::STATUS_TRANSFERRED_OUT,
                        'danger' => Admission::STATUS_EXPIRED,
                        'info' => Admission::STATUS_LAMA,
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('admitted_at')->dateTime('M j, H:i')->sortable(),
                Tables\Columns\TextColumn::make('discharged_at')->dateTime('M j, H:i')->toggleable()->placeholder('—'),
                Tables\Columns\TextColumn::make('branch.name')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    Admission::STATUS_ACTIVE => 'Active',
                    Admission::STATUS_DISCHARGED => 'Discharged',
                    Admission::STATUS_TRANSFERRED_OUT => 'Transferred out',
                    Admission::STATUS_LAMA => 'LAMA',
                    Admission::STATUS_EXPIRED => 'Expired',
                ]),
                Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('assignBed')
                    ->label('Assign bed')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(fn (Admission $r) => $r->isActive() && ! $r->currentBedStay)
                    ->form([
                        Forms\Components\Select::make('bed_id')
                            ->label('Bed')
                            ->options(fn () => Bed::query()
                                ->with('ward')
                                ->where('status', Bed::STATUS_AVAILABLE)
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Bed $b) => [
                                    $b->id => ($b->ward?->name ?? 'Ward').' / '.$b->code,
                                ]))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (Admission $r, array $data) {
                        $bed = Bed::query()->findOrFail($data['bed_id']);
                        try {
                            app(BedAssignmentService::class)->assign($r, $bed, auth()->user(), 'Manual assignment');
                            Notification::make()->title('Bed assigned')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Assignment failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('transfer')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn (Admission $r) => $r->isActive() && $r->currentBedStay)
                    ->form([
                        Forms\Components\Select::make('bed_id')
                            ->label('New bed')
                            ->options(fn () => Bed::query()
                                ->with('ward')
                                ->where('status', Bed::STATUS_AVAILABLE)
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Bed $b) => [
                                    $b->id => ($b->ward?->name ?? 'Ward').' / '.$b->code,
                                ]))
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('reason')->placeholder('e.g. Patient condition deteriorated')->required(),
                    ])
                    ->action(function (Admission $r, array $data) {
                        $bed = Bed::query()->findOrFail($data['bed_id']);
                        try {
                            app(BedAssignmentService::class)->transfer($r, $bed, auth()->user(), $data['reason']);
                            Notification::make()->title('Transferred')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Transfer failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('discharge')
                    ->label('Discharge')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Admission $r) => $r->isActive())
                    ->form([
                        Forms\Components\Select::make('final_status')
                            ->label('Discharge type')
                            ->options([
                                Admission::STATUS_DISCHARGED => 'Discharged (recovered)',
                                Admission::STATUS_LAMA => 'Left against medical advice',
                                Admission::STATUS_TRANSFERRED_OUT => 'Transferred to another facility',
                                Admission::STATUS_EXPIRED => 'Expired',
                            ])
                            ->required()
                            ->default(Admission::STATUS_DISCHARGED)
                            ->native(false),
                        Forms\Components\Textarea::make('summary')
                            ->label('Discharge summary')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (Admission $r, array $data) {
                        try {
                            $result = app(AdmissionService::class)->discharge(
                                $r, auth()->user(), $data['summary'], $data['final_status']
                            );
                            Notification::make()
                                ->title('Discharged. Final bill: '.number_format($result['total'], 3).' KWD')
                                ->body('Final Visit #'.$result['final_visit']->id.' is awaiting payment.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Discharge failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BedStaysRelationManager::class,
            RelationManagers\ChargesRelationManager::class,
            RelationManagers\RoundsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmissions::route('/'),
            'create' => Pages\CreateAdmission::route('/create'),
            'view' => Pages\ViewAdmission::route('/{record}'),
            'edit' => Pages\EditAdmission::route('/{record}/edit'),
        ];
    }
}
