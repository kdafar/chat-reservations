<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoctorCompensationProfileResource\Pages;
use App\Models\Doctor;
use App\Models\DoctorCompensationProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DoctorCompensationProfileResource extends Resource
{
    protected static ?string $model = DoctorCompensationProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Clinic — Finance';

    protected static ?string $modelLabel = 'Doctor Compensation Profile';

    protected static ?string $pluralModelLabel = 'Doctor Compensation Profiles';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Profile')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('doctor_id')
                        ->label('Doctor')
                        ->relationship('doctor', 'id')
                        ->getOptionLabelFromRecordUsing(fn (Doctor $d) => $d->name ?? ('Doctor #'.$d->id))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (string $operation) => $operation === 'edit'),

                    Forms\Components\Select::make('type')
                        ->label('Type')
                        ->options([
                            'salary' => 'Salary',
                            'percentage' => 'Percentage',
                        ])
                        ->native(false)
                        ->default('percentage')
                        ->required(),

                    Forms\Components\Select::make('basis')
                        ->label('Basis')
                        ->options([
                            'fees_only' => 'Fees Only (fees - discount)',
                            'net_profit' => 'Net Profit',
                        ])
                        ->native(false)
                        ->default('fees_only')
                        ->required(),

                    Forms\Components\TextInput::make('percentage_rate')
                        ->label('Percentage Rate')
                        ->numeric()
                        ->step('0.001')
                        ->nullable()
                        ->helperText('Only used when Type = Percentage.')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'percentage'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

            Tables\Columns\TextColumn::make('doctor_id')
                ->label('Doctor')
                ->formatStateUsing(fn ($state) => Doctor::query()->find($state)?->name ?? ('Doctor #'.$state))
                ->searchable(),

            Tables\Columns\TextColumn::make('type')
                ->badge(),

            Tables\Columns\TextColumn::make('basis')
                ->badge(),

            Tables\Columns\TextColumn::make('percentage_rate')
                ->label('Rate')
                ->numeric(3),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),
        ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDoctorCompensationProfiles::route('/'),
            'create' => Pages\CreateDoctorCompensationProfile::route('/create'),
            'edit' => Pages\EditDoctorCompensationProfile::route('/{record}/edit'),
        ];
    }
}
