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

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.clinic_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.doctor_compensation_profile.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.doctor_compensation_profile.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.doctor_compensation_profile.label_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('clinic_misc.doctor_compensation_profile.section_profile'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('doctor_id')
                        ->label(__('clinic_misc.doctor_compensation_profile.doctor'))
                        ->relationship('doctor', 'id')
                        ->getOptionLabelFromRecordUsing(fn (Doctor $d) => $d->name ?? __('clinic_misc.doctor_compensation_profile.doctor_hash', ['id' => $d->id]))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (string $operation) => $operation === 'edit'),

                    Forms\Components\Select::make('type')
                        ->label(__('clinic_misc.doctor_compensation_profile.type'))
                        ->options([
                            'salary' => __('clinic_misc.doctor_compensation_profile.type_salary'),
                            'percentage' => __('clinic_misc.doctor_compensation_profile.type_percentage'),
                        ])
                        ->native(false)
                        ->default('percentage')
                        ->required(),

                    Forms\Components\Select::make('basis')
                        ->label(__('clinic_misc.doctor_compensation_profile.basis'))
                        ->options([
                            'fees_only' => __('clinic_misc.doctor_compensation_profile.basis_fees_only'),
                            'net_profit' => __('clinic_misc.doctor_compensation_profile.basis_net_profit'),
                        ])
                        ->native(false)
                        ->default('fees_only')
                        ->required(),

                    Forms\Components\TextInput::make('percentage_rate')
                        ->label(__('clinic_misc.doctor_compensation_profile.percentage_rate'))
                        ->numeric()
                        ->step('0.001')
                        ->nullable()
                        ->helperText(__('clinic_misc.doctor_compensation_profile.percentage_rate_help'))
                        ->visible(fn (Forms\Get $get) => $get('type') === 'percentage'),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('clinic_misc.doctor_compensation_profile.active'))
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
                ->label(__('clinic_misc.doctor_compensation_profile.doctor'))
                ->formatStateUsing(fn ($state) => Doctor::query()->find($state)?->name ?? __('clinic_misc.doctor_compensation_profile.doctor_hash', ['id' => $state]))
                ->searchable(),

            Tables\Columns\TextColumn::make('type')
                ->badge(),

            Tables\Columns\TextColumn::make('basis')
                ->badge(),

            Tables\Columns\TextColumn::make('percentage_rate')
                ->label(__('clinic_misc.doctor_compensation_profile.rate'))
                ->numeric(3),

            Tables\Columns\IconColumn::make('is_active')
                ->label(__('clinic_misc.doctor_compensation_profile.active'))
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
