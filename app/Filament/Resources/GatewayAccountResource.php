<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GatewayAccountResource\Pages;
use App\Models\Gateway;
use App\Models\GatewayAccount;
use Filament\Forms;
use Filament\Forms\Components as F;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns as C;

class GatewayAccountResource extends Resource
{
    protected static ?string $model = GatewayAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'Gateway Accounts';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            F\Section::make('Gateway')->schema([
                F\Select::make('gateway_id')
                    ->label('Gateway')
                    ->options(fn () => Gateway::query()->pluck('name', 'id'))
                    ->searchable()->required(),

                F\TextInput::make('display_name')
                    ->required()->maxLength(120),

                F\KeyValue::make('credentials')
                    ->label('Credentials (JSON)')
                    ->keyLabel('Key')->valueLabel('Value')->reorderable(),

                F\TextInput::make('currency')->default('KWD')->required(),

                F\Toggle::make('is_active')->default(true),
                F\Toggle::make('is_default')->default(false),
            ])->columns(2),

            F\Section::make('Ownership')->schema([
                F\Radio::make('owner_type')
                    ->options([
                        'system' => 'System',
                        'partner' => 'Partner',
                        'branch' => 'Branch',
                        'service' => 'Service',
                    ])
                    ->default('system')
                    ->inline()
                    ->live(),

                F\Select::make('partner_id')
                    ->relationship('partner', 'name')
                    ->searchable()->preload()
                    ->visible(fn (Get $get) => $get('owner_type') === 'partner')
                    ->required(fn (Get $get) => $get('owner_type') === 'partner'),

                F\Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->searchable()->preload()
                    ->visible(fn (Get $get) => $get('owner_type') === 'branch')
                    ->required(fn (Get $get) => $get('owner_type') === 'branch'),

                F\Select::make('service_id')
                    ->relationship('service', 'name')
                    ->searchable()->preload()
                    ->visible(fn (Get $get) => $get('owner_type') === 'service')
                    ->required(fn (Get $get) => $get('owner_type') === 'service'),
            ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                C\TextColumn::make('display_name')->label('Account')->searchable(),
                C\TextColumn::make('gateway.name')->label('Gateway')->sortable(),
                C\TextColumn::make('currency')->sortable(),
                C\BadgeColumn::make('owner_type')
                    ->colors([
                        'primary' => 'system',
                        'warning' => 'partner',
                        'success' => 'branch',
                        'info' => 'service',
                    ]),
                C\TextColumn::make('owner_label')
                    ->label('Owner')
                    ->state(function (GatewayAccount $r) {
                        return match ($r->owner_type) {
                            'partner' => optional($r->partner)->name,
                            'branch' => optional($r->branch)->name,
                            'service' => optional($r->service)->name,
                            default => '—',
                        };
                    })
                    ->toggleable(),
                C\IconColumn::make('is_active')->boolean()->label('Active'),
                C\IconColumn::make('is_default')->boolean()->label('Default'),
                C\TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('owner_type')
                    ->options(['system' => 'System', 'partner' => 'Partner', 'branch' => 'Branch', 'service' => 'Service']),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGatewayAccounts::route('/'),
            'create' => Pages\CreateGatewayAccount::route('/create'),
            'edit' => Pages\EditGatewayAccount::route('/{record}/edit'),
        ];
    }
}
