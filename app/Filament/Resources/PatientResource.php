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

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Clinic — Operations';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')
                ->schema([
                    Forms\Components\Select::make('partner_id')
                        ->label('Clinic')
                        ->options(Partner::all()->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->default(fn () => Partner::first()?->id),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label('Full Name')
                        ->placeholder('Patient Name'),

                    Forms\Components\TextInput::make('phone')
                        ->label('Mobile Number')
                        ->tel()
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->placeholder('Optional'),
                ])->columns(2),

            Forms\Components\Section::make('Demographics')
                ->schema([
                    Forms\Components\DatePicker::make('dob')
                        ->label('Date of Birth')
                        ->maxDate(now())
                        ->native(false),

                    Forms\Components\Select::make('gender')
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                        ]),

                    Forms\Components\Select::make('blood_group')
                        ->label('Blood Group')
                        ->options([
                            'A+' => 'A+', 'A-' => 'A-',
                            'B+' => 'B+', 'B-' => 'B-',
                            'AB+' => 'AB+', 'AB-' => 'AB-',
                            'O+' => 'O+', 'O-' => 'O-',
                        ]),

                    Forms\Components\TextInput::make('civil_id')
                        ->label('Civil ID / National ID'),
                ])->columns(4),

            Forms\Components\Section::make('Medical Profile')
                ->schema([
                    Forms\Components\Textarea::make('allergies')
                        ->label('Allergies')
                        ->placeholder('e.g. Penicillin, Peanuts, Latex')
                        ->helperText('This will appear on prescriptions.')
                        ->rows(2)
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'bg-red-50 border-red-300 text-red-900']),

                    Forms\Components\Textarea::make('medical_alerts')
                        ->label('Other Medical Alerts')
                        ->placeholder('e.g. Diabetic, Pacemaker')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Admin Notes')
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
                    ->label('Age')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->age.' yrs' : '-'),

                Tables\Columns\TextColumn::make('gender')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
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
                    ->label('Blood')
                    ->badge()
                    ->color('danger'),

                // Visual Warning if Allergies exist
                Tables\Columns\IconColumn::make('allergies')
                    ->label('Allergy')
                    ->boolean()
                    ->trueIcon('heroicon-s-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->getStateUsing(fn ($record) => ! empty($record->allergies)),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->label('Registered')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('Clinic')
                    ->relationship('partner', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // This allows viewing visits from the patient page
            \App\Filament\Resources\PatientResource\RelationManagers\VisitsRelationManager::class,
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
