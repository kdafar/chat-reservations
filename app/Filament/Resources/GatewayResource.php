<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GatewayResource\Pages;
use App\Models\Gateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable as FilamentTranslatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GatewayResource extends Resource
{
    use FilamentTranslatable;

    protected static ?string $model = Gateway::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 20;

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Display Name')
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->helperText('A short, customer-facing description of the payment method.'),

                Forms\Components\FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->directory('gateways')
                    ->imageEditor(),
            ]),

            Forms\Components\Section::make('Configuration')->schema([
                Forms\Components\Select::make('driver')
                    ->options([
                        'myfatoorah' => 'MyFatoorah',
                        'tap' => 'Tap',
                        'stripe' => 'Stripe',
                        'cash' => 'Cash',
                    ])
                    ->required()
                    ->native(false)
                    ->searchable(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Disable this to hide the gateway from all checkouts.'),

                Forms\Components\Toggle::make('is_system')
                    ->label('System Gateway')
                    ->helperText('If ON, this gateway type can have system-level (fallback) accounts.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url')
                    ->label('Logo')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('driver')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\IconColumn::make('is_system')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGateways::route('/'),
            'create' => Pages\CreateGateway::route('/create'),
            'edit' => Pages\EditGateway::route('/{record}/edit'),
        ];
    }
}
