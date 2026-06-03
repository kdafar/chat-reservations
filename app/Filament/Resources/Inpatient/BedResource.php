<?php

namespace App\Filament\Resources\Inpatient;

use App\Filament\Resources\Inpatient\BedResource\Pages;
use App\Models\Inpatient\Bed;
use App\Models\Inpatient\Ward;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BedResource extends Resource
{
    protected static ?string $model = Bed::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.inpatient');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('ward_id')
                ->relationship('ward', 'name')
                ->required()
                ->preload()
                ->searchable()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    // Mirror the ward's branch onto the bed for fast queries.
                    $ward = $state ? Ward::query()->find($state) : null;
                    if ($ward) {
                        $set('branch_id', $ward->branch_id);
                    }
                }),

            Forms\Components\Select::make('branch_id')
                ->relationship('branch', 'name')
                ->required()
                ->disabled()
                ->dehydrated(),

            Forms\Components\TextInput::make('code')->required()->maxLength(50)
                ->helperText('e.g. "A-101"'),

            Forms\Components\Select::make('status')
                ->options([
                    Bed::STATUS_AVAILABLE => 'Available',
                    Bed::STATUS_OCCUPIED => 'Occupied',
                    Bed::STATUS_RESERVED => 'Reserved',
                    Bed::STATUS_CLEANING => 'Cleaning',
                    Bed::STATUS_MAINTENANCE => 'Maintenance',
                ])
                ->default(Bed::STATUS_AVAILABLE)
                ->disabled(fn ($record) => $record && $record->status === Bed::STATUS_OCCUPIED)
                ->helperText('Locked while occupied — discharge or transfer the patient first.')
                ->native(false),

            Forms\Components\TextInput::make('daily_rate_override')
                ->numeric()
                ->step(0.001)
                ->prefix(config('app.currency', 'KWD'))
                ->helperText('Leave blank to use the ward default.'),

            Forms\Components\TagsInput::make('features')
                ->placeholder('oxygen, ventilator, isolation')
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ward_id')
            ->columns([
                Tables\Columns\TextColumn::make('ward.name')->label('Ward')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => Bed::STATUS_AVAILABLE,
                        'danger' => Bed::STATUS_OCCUPIED,
                        'warning' => Bed::STATUS_RESERVED,
                        'info' => Bed::STATUS_CLEANING,
                        'gray' => Bed::STATUS_MAINTENANCE,
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('daily_rate_override')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state, $record) => $state ?: $record->ward?->daily_rate)
                    ->money(config('app.currency', 'KWD')),
                Tables\Columns\TextColumn::make('features')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ward_id')->relationship('ward', 'name'),
                Tables\Filters\SelectFilter::make('status')->options([
                    Bed::STATUS_AVAILABLE => 'Available',
                    Bed::STATUS_OCCUPIED => 'Occupied',
                    Bed::STATUS_RESERVED => 'Reserved',
                    Bed::STATUS_CLEANING => 'Cleaning',
                    Bed::STATUS_MAINTENANCE => 'Maintenance',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('free')
                    ->label('Mark available')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Bed $r) => in_array($r->status, [Bed::STATUS_CLEANING, Bed::STATUS_MAINTENANCE], true))
                    ->requiresConfirmation()
                    ->action(fn (Bed $r) => $r->update(['status' => Bed::STATUS_AVAILABLE])),

                Tables\Actions\Action::make('maintenance')
                    ->label('Mark maintenance')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('gray')
                    ->visible(fn (Bed $r) => $r->status === Bed::STATUS_AVAILABLE)
                    ->requiresConfirmation()
                    ->action(fn (Bed $r) => $r->update(['status' => Bed::STATUS_MAINTENANCE])),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('free')
                    ->label('Mark available')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $r) {
                            if (in_array($r->status, [Bed::STATUS_CLEANING, Bed::STATUS_MAINTENANCE], true)) {
                                $r->update(['status' => Bed::STATUS_AVAILABLE]);
                            }
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeds::route('/'),
            'create' => Pages\CreateBed::route('/create'),
            'edit' => Pages\EditBed::route('/{record}/edit'),
        ];
    }
}
