<?php

namespace App\Filament\Resources\Inpatient;

use App\Filament\Resources\Inpatient\WardResource\Pages;
use App\Models\Inpatient\Ward;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WardResource extends Resource
{
    protected static ?string $model = Ward::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.inpatient');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('branch_id')
                ->relationship('branch', 'name')
                ->required()
                ->preload()
                ->searchable(),

            Forms\Components\Select::make('partner_id')
                ->relationship('branch.partner', 'name')
                ->visible(false) // auto-derived; populated on save
                ->dehydrated(false),

            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->maxLength(50),

            Forms\Components\Select::make('ward_type')
                ->options([
                    Ward::TYPE_GENERAL => 'General',
                    Ward::TYPE_ICU => 'ICU',
                    Ward::TYPE_PEDIATRIC => 'Pediatric',
                    Ward::TYPE_MATERNITY => 'Maternity',
                    Ward::TYPE_ISOLATION => 'Isolation',
                    Ward::TYPE_VIP => 'VIP',
                ])
                ->required()
                ->native(false),

            Forms\Components\TextInput::make('daily_rate')
                ->numeric()
                ->required()
                ->step(0.001)
                ->prefix(config('app.currency', 'KWD'))
                ->helperText('Default daily charge; can be overridden per bed.'),

            Forms\Components\Select::make('gender_restriction')
                ->options([
                    Ward::GENDER_ANY => 'Any',
                    Ward::GENDER_MALE => 'Male only',
                    Ward::GENDER_FEMALE => 'Female only',
                ])
                ->default(Ward::GENDER_ANY)
                ->native(false),

            Forms\Components\Toggle::make('is_active')->default(true),

            Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->toggleable(),
                Tables\Columns\BadgeColumn::make('ward_type')
                    ->colors([
                        'gray' => Ward::TYPE_GENERAL,
                        'danger' => Ward::TYPE_ICU,
                        'info' => Ward::TYPE_PEDIATRIC,
                        'success' => Ward::TYPE_MATERNITY,
                        'warning' => Ward::TYPE_ISOLATION,
                        'primary' => Ward::TYPE_VIP,
                    ]),
                Tables\Columns\TextColumn::make('daily_rate')->money(config('app.currency', 'KWD'))->sortable(),
                Tables\Columns\TextColumn::make('beds_count')->counts('beds')->label('Beds'),
                Tables\Columns\TextColumn::make('available_beds_count')
                    ->counts('availableBeds')
                    ->label('Available'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('ward_type')->options([
                    Ward::TYPE_GENERAL => 'General',
                    Ward::TYPE_ICU => 'ICU',
                    Ward::TYPE_PEDIATRIC => 'Pediatric',
                    Ward::TYPE_MATERNITY => 'Maternity',
                    Ward::TYPE_ISOLATION => 'Isolation',
                    Ward::TYPE_VIP => 'VIP',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWards::route('/'),
            'create' => Pages\CreateWard::route('/create'),
            'edit' => Pages\EditWard::route('/{record}/edit'),
        ];
    }
}
