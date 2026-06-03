<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Models\PatientFile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PatientFilesRelationManager extends RelationManager
{
    /**
     * IMPORTANT:
     * The Patient model must expose a `files()` HasMany relation to App\Models\PatientFile.
     */
    protected static string $relationship = 'files';

    protected static ?string $title = 'Files';

    protected static ?string $recordTitleAttribute = 'original_filename';

    /** Map enum value -> human label. */
    protected static function categoryOptions(): array
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

    /** Filament badge color per category. */
    protected static function categoryColor(?string $state): string
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

    public function form(Form $form): Form
    {
        $patient = $this->getOwnerRecord();

        return $form->schema([
            Forms\Components\FileUpload::make('file_path')
                ->label('File')
                ->disk('local')
                ->directory('patient-files/'.$patient->id)
                ->preserveFilenames()
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/heic',
                ])
                ->maxSize(20480)
                ->required()
                // Capture metadata about the uploaded file the moment the upload completes.
                // FileUpload's state is an array of TemporaryUploadedFile objects keyed by a hash.
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    if (! $state) {
                        return;
                    }

                    $file = is_array($state) ? reset($state) : $state;

                    if ($file instanceof TemporaryUploadedFile) {
                        $set('original_filename', $file->getClientOriginalName());
                        $set('mime_type', $file->getMimeType());
                        $set('size_bytes', $file->getSize());
                    }
                })
                // Save the original filename so the downloader can serve it correctly later.
                ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) use ($patient): string {
                    return 'patient-files/'.$patient->id.'/'.Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
                })
                ->columnSpanFull(),

            // Hidden fields populated by the FileUpload afterStateUpdated callback above.
            Forms\Components\Hidden::make('original_filename'),
            Forms\Components\Hidden::make('mime_type'),
            Forms\Components\Hidden::make('size_bytes'),

            Forms\Components\Select::make('category')
                ->label('Category')
                ->options(self::categoryOptions())
                ->required()
                ->default('other')
                ->native(false),

            Forms\Components\Select::make('visit_id')
                ->label('Link to visit (optional)')
                ->options(fn () => $patient->visits()
                    ->orderByDesc('created_at')
                    ->get()
                    ->mapWithKeys(fn ($v) => [
                        $v->id => '#'.$v->id.' — '.optional($v->created_at)->format('Y-m-d'),
                    ]))
                ->searchable()
                ->nullable(),

            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->icon('heroicon-o-document'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Upload File')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn () => auth()->user()?->can('patient_files_upload') ?? false)
                    ->mutateFormDataUsing(function (array $data): array {
                        $patient = $this->getOwnerRecord();
                        $data['patient_id'] = $patient->id;
                        $data['branch_id'] = $patient->branch_id ?? null;
                        $data['uploaded_by_user_id'] = auth()->id();

                        // Defensive: if afterStateUpdated didn't capture metadata
                        // (e.g. browser quirk), recompute from the persisted file.
                        if (empty($data['original_filename']) && ! empty($data['file_path'])) {
                            $disk = Storage::disk('local');
                            if ($disk->exists($data['file_path'])) {
                                $data['original_filename'] = basename($data['file_path']);
                                $data['mime_type'] = $data['mime_type'] ?? $disk->mimeType($data['file_path']);
                                $data['size_bytes'] = $data['size_bytes'] ?? $disk->size($data['file_path']);
                            }
                        }

                        return $data;
                    }),
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

                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Select::make('category')
                            ->options(self::categoryOptions())
                            ->required()
                            ->native(false),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn () => auth()->user()?->can('patient_files_upload') ?? false),

                Tables\Actions\DeleteAction::make()
                    ->visible(function () {
                        $user = auth()->user();
                        if (! $user) {
                            return false;
                        }

                        return $user->hasRole(['admin', 'super_admin', 'clinic_admin'])
                            || $user->can('patient_files_delete');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin', 'clinic_admin']) ?? false),
            ]);
    }
}
