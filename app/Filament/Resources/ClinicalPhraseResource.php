<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicalPhraseResource\Pages;
use App\Models\Branch;
use App\Models\ClinicalPhrase;
use App\Models\Doctor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClinicalPhraseResource extends Resource
{
    protected static ?string $model = ClinicalPhrase::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'Clinical Library';

    protected static ?string $navigationLabel = 'Quick Phrases';

    protected static ?int $navigationSort = 1;

    /** Human labels for the targetable clinical fields. */
    protected static function fieldOptions(): array
    {
        return [
            'chief_complaint' => 'Chief complaint',
            'examination' => 'Examination',
            'diagnosis' => 'Diagnosis',
            'patient_instructions' => 'Patient instructions',
            'prescriptions' => 'Prescription',
            'lab_requests' => 'Lab requests',
        ];
    }

    public static function form(Form $form): Form
    {
        $locale = app()->getLocale();

        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('field')
                    ->options(static::fieldOptions())
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('locale')
                    ->label('Language')
                    ->options(['en' => 'English', 'ar' => 'العربية'])
                    ->placeholder('Any language')
                    ->native(false),
                Forms\Components\TextInput::make('label')
                    ->label('Chip label (button text)')
                    ->required()->maxLength(191),
                Forms\Components\Textarea::make('body')
                    ->label('Inserted text')
                    ->required()->rows(3)->maxLength(5000)
                    ->columnSpanFull(),
                Forms\Components\Select::make('scope')
                    ->options(['clinic' => 'Shared (clinic)', 'doctor' => 'Personal (doctor)'])
                    ->default('clinic')->required()->live()->native(false),
                Forms\Components\Select::make('doctor_id')
                    ->label('Doctor')
                    ->options(fn () => Doctor::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('scope') === 'doctor')
                    ->required(fn (Forms\Get $get) => $get('scope') === 'doctor'),
                Forms\Components\Select::make('branch_id')
                    ->label('Branch (blank = all branches)')
                    ->options(fn () => Branch::all()->mapWithKeys(fn ($b) => [$b->id => (string) $b->getTranslation('name', $locale)]))
                    ->searchable(),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('field')
                    ->formatStateUsing(fn ($state) => static::fieldOptions()[$state] ?? $state)
                    ->badge()->sortable(),
                Tables\Columns\TextColumn::make('label')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('body')->limit(60)->toggleable()->wrap(),
                Tables\Columns\BadgeColumn::make('scope')
                    ->colors(['primary' => 'clinic', 'warning' => 'doctor']),
                Tables\Columns\TextColumn::make('doctor.name')->label('Doctor')->toggleable(),
                Tables\Columns\TextColumn::make('locale')->label('Lang')->badge()->placeholder('any'),
                Tables\Columns\TextColumn::make('usage_count')->label('Used')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('usage_count', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('field')->options(static::fieldOptions()),
                Tables\Filters\SelectFilter::make('scope')->options(['clinic' => 'Shared', 'doctor' => 'Personal']),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClinicalPhrases::route('/'),
            'create' => Pages\CreateClinicalPhrase::route('/create'),
            'edit' => Pages\EditClinicalPhrase::route('/{record}/edit'),
        ];
    }
}
