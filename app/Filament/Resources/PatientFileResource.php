<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientFileResource\Pages;
use App\Filament\Resources\PatientFileResource\RelationManagers\AccessLogsRelationManager;
use App\Models\PatientFile;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PatientFileResource extends Resource
{
    protected static ?string $model = PatientFile::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_operations');
    }

    public static function getNavigationLabel(): string
    {
        return 'Patient Files';
    }

    public static function getModelLabel(): string
    {
        return 'Patient File';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Patient Files';
    }

    /** Files are only uploaded from a patient page; this resource is a search/audit surface. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Map enum value -> human label. Mirrors PatientFilesRelationManager. */
    public static function categoryOptions(): array
    {
        return [
            'lab_report' => 'Lab Report',
            'prescription' => 'Prescription',
            'imaging' => 'Imaging',
            'insurance_card' => 'Insurance Card',
            'consent_form' => 'Consent Form',
            'referral' => 'Referral',
            'discharge_summary' => 'Discharge Summary',
            'other' => 'Other',
        ];
    }

    public static function categoryColor(?string $state): string
    {
        return match ($state) {
            'lab_report' => 'info',
            'prescription' => 'success',
            'imaging' => 'warning',
            'insurance_card' => 'primary',
            'consent_form' => 'gray',
            'referral' => 'info',
            'discharge_summary' => 'gray',
            default => 'gray',
        };
    }

    public static function form(Form $form): Form
    {
        // Edit-only form: file payload is immutable, only metadata can change.
        return $form->schema([
            Forms\Components\Select::make('category')
                ->options(self::categoryOptions())
                ->required()
                ->native(false),
            Forms\Components\Textarea::make('notes')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->icon('heroicon-o-document'),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::categoryOptions()[$state] ?? $state)
                    ->color(fn (?string $state) => self::categoryColor($state)),

                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state, $record) => $record->display_size)
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit.id')
                    ->label('Visit')
                    ->placeholder('—')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? '#'.$state : '—'),

                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->multiple()
                    ->options(self::categoryOptions()),

                Tables\Filters\SelectFilter::make('patient_id')
                    ->label('Patient')
                    ->relationship('patient', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('uploaded_by_user_id')
                    ->label('Uploaded By')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('has_visit')
                    ->label('Linked to visit')
                    ->placeholder('Any')
                    ->trueLabel('Linked')
                    ->falseLabel('Standalone')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('visit_id'),
                        false: fn ($q) => $q->whereNull('visit_id'),
                        blank: fn ($q) => $q,
                    ),

                Tables\Filters\Filter::make('uploaded_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (PatientFile $record) => route('admin.patient-files.download', [
                        'patientFile' => $record->id,
                        'inline' => 1,
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn () => auth()->user()?->can('patient_files_view') ?? false),

                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (PatientFile $record) => route('admin.patient-files.download', [
                        'patientFile' => $record->id,
                    ]))
                    ->visible(fn () => auth()->user()?->can('patient_files_view') ?? false),

                Tables\Actions\DeleteAction::make()
                    ->visible(function () {
                        $user = auth()->user();
                        if (! $user) {
                            return false;
                        }

                        return $user->hasRole(['admin', 'super_admin', 'clinic_admin'])
                            || $user->can('patient_files_delete');
                    }),
                Tables\Actions\RestoreAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin', 'clinic_admin']) ?? false),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin', 'clinic_admin']) ?? false),
                Tables\Actions\RestoreBulkAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin', 'clinic_admin']) ?? false),
                Tables\Actions\ForceDeleteBulkAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient', 'uploadedBy', 'visit'])
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AccessLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatientFiles::route('/'),
        ];
    }
}
