<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientResource\Pages;
use App\Models\Partner;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.patient.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.patient.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.patient.label_plural');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('clinic_patient.sections.identity'))
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label(__('clinic_patient.fields.clinic'))
                        ->options(Partner::all()->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->default(fn () => Partner::first()?->id),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label(__('clinic_patient.fields.full_name'))
                        ->placeholder(__('clinic_patient.placeholders.patient_name')),

                    Forms\Components\TextInput::make('phone')
                        ->label(__('clinic_patient.fields.mobile_number'))
                        ->tel()
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->placeholder(__('clinic_patient.placeholders.optional')),
                ])->columns(2),

            Forms\Components\Section::make(__('clinic_patient.sections.demographics'))
                ->schema([
                    Forms\Components\DatePicker::make('dob')
                        ->label(__('clinic_patient.fields.date_of_birth'))
                        ->maxDate(now())
                        ->native(false),

                    Forms\Components\Select::make('gender')
                        ->label(__('clinic_patient.fields.gender'))
                        ->options([
                            'male' => __('clinic_patient.gender.male'),
                            'female' => __('clinic_patient.gender.female'),
                        ]),

                    Forms\Components\Select::make('blood_group')
                        ->label(__('clinic_patient.fields.blood_group'))
                        ->options([
                            'A+' => 'A+', 'A-' => 'A-',
                            'B+' => 'B+', 'B-' => 'B-',
                            'AB+' => 'AB+', 'AB-' => 'AB-',
                            'O+' => 'O+', 'O-' => 'O-',
                        ]),

                    Forms\Components\TextInput::make('civil_id')
                        ->label(__('clinic_patient.fields.civil_id')),
                ])->columns(4),

            Forms\Components\Section::make(__('clinic_patient.sections.medical_profile'))
                ->schema([
                    Forms\Components\Textarea::make('allergies')
                        ->label(__('clinic_patient.fields.allergies'))
                        ->placeholder(__('clinic_patient.placeholders.allergies'))
                        ->helperText(__('clinic_patient.helpers.allergies'))
                        ->rows(2)
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'bg-red-50 border-red-300 text-red-900']),

                    Forms\Components\Textarea::make('medical_alerts')
                        ->label(__('clinic_patient.fields.medical_alerts'))
                        ->placeholder(__('clinic_patient.placeholders.medical_alerts'))
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('clinic_patient.fields.admin_notes'))
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-m-phone'),

                Tables\Columns\TextColumn::make('dob')
                    ->label(__('clinic_patient.fields.age'))
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->age.' '.__('clinic_patient.yrs') : '-'),

                Tables\Columns\TextColumn::make('gender')
                    ->label(__('clinic_patient.fields.gender'))
                    ->formatStateUsing(fn (string $state): string => $state ? __('clinic_patient.gender.'.$state) : '')
                    ->icon(fn (string $state): string => match ($state) {
                        'male' => 'heroicon-m-user',
                        'female' => 'heroicon-m-user', // You can use gender specific icons if available
                        default => 'heroicon-m-user',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'danger', // Pinkish usually represented by danger or custom color
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('blood_group')
                    ->label(__('clinic_patient.fields.blood'))
                    ->badge()
                    ->color('danger'),

                // Visual Warning if Allergies exist
                Tables\Columns\IconColumn::make('allergies')
                    ->label(__('clinic_patient.fields.allergy'))
                    ->boolean()
                    ->trueIcon('heroicon-s-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->getStateUsing(fn ($record) => ! empty($record->allergies)),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->label(__('clinic_patient.fields.registered'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label(__('clinic_patient.fields.clinic'))
                    ->relationship('partner', 'name'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                \App\Filament\Imports\ExcelImportAction::make()
                    ->importer(\App\Filament\Imports\PatientImporter::class)
                    ->label('Import'),
                \App\Filament\Exports\ExcelExportActions::header(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)
                    ->before(function (\App\Models\Patient $record, Tables\Actions\ForceDeleteAction $action) {
                        $blockers = self::patientDeletionBlockers($record);
                        if (! empty($blockers)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot permanently delete this patient')
                                ->body('Related records still exist: '.implode(', ', $blockers).'. Delete or reassign those first.')
                                ->danger()
                                ->persistent()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                \App\Filament\Exports\ExcelExportActions::bulk(),
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)
                    ->before(function ($records, Tables\Actions\ForceDeleteBulkAction $action) {
                        $blocked = [];
                        foreach ($records as $record) {
                            $reasons = self::patientDeletionBlockers($record);
                            if (! empty($reasons)) {
                                $blocked[] = "#{$record->id} ({$record->name}): ".implode(', ', $reasons);
                            }
                        }
                        if (! empty($blocked)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot permanently delete '.count($blocked).' patient(s)')
                                ->body(implode("\n", $blocked))
                                ->danger()
                                ->persistent()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->emptyStateHeading(__('resources.patient.empty_heading'))
            ->emptyStateDescription(__('resources.patient.empty_description'))
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    /**
     * Return human-readable list of relations blocking a hard-delete.
     * If any are present, ForceDelete is refused to prevent silent data
     * loss (cascade on visits/admissions) or mid-delete FK violations
     * (insurance policies/patient files).
     */
    public static function patientDeletionBlockers(\App\Models\Patient $p): array
    {
        $blockers = [];
        if ($p->visits()->withTrashed()->exists()) {
            $blockers[] = $p->visits()->withTrashed()->count().' visits';
        }
        if (\App\Models\Booking::query()->where('patient_id', $p->id)->exists()) {
            $blockers[] = 'bookings';
        }
        if (class_exists(\App\Models\Insurance\PatientInsurancePolicy::class)
            && \App\Models\Insurance\PatientInsurancePolicy::query()->where('patient_id', $p->id)->exists()
        ) {
            $blockers[] = 'insurance policies';
        }
        if (class_exists(\App\Models\PatientFile::class)
            && \App\Models\PatientFile::query()->where('patient_id', $p->id)->exists()
        ) {
            $blockers[] = 'patient files';
        }
        if (class_exists(\App\Models\Inpatient\Admission::class)
            && \App\Models\Inpatient\Admission::query()->where('patient_id', $p->id)->exists()
        ) {
            $blockers[] = 'admissions';
        }
        return $blockers;
    }

    public static function getRelations(): array
    {
        return [
            // This allows viewing visits from the patient page
            \App\Filament\Resources\PatientResource\RelationManagers\VisitsRelationManager::class,
            \App\Filament\Resources\PatientResource\RelationManagers\PatientFilesRelationManager::class,
            \App\Filament\Resources\Concerns\ActivityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
        ];
    }
}
