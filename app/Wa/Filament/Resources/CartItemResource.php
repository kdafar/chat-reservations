<?php

namespace App\Wa\Filament\Resources;

use App\Wa\Filament\Resources\CartItemResource\Pages;
use App\Wa\Hub\Models\CartItem;
use App\Wa\Hub\Models\WhatsappSession; // Import WhatsappSession
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CartItemResource extends Resource
{
    protected static ?string $model = CartItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    // Group under "WhatsApp Vendor Management"
    protected static ?string $navigationGroup = 'WhatsApp Vendor Management';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cart Item Details')
                    ->schema([
                        Select::make('whatsapp_session_id')
                            ->label('WhatsApp Session')
                            ->options(WhatsappSession::all()->pluck('customer_phone_number', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('item_id_from_restaurant')
                            ->label('Item ID from Restaurant')
                            ->maxLength(255),
                        TextInput::make('item_name')
                            ->label('Item Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Textarea::make('variations')
                            ->label('Variations (JSON)')
                            ->helperText('Enter variations as a JSON string (e.g., {"size": "Large", "milk": "Whole"})')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('whatsappSession.customer_phone_number')
                    ->label('Customer Phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price')
                    ->money('usd') // Assuming USD, adjust as needed
                    ->sortable(),
                TextColumn::make('variations')
                    ->label('Variations')
                    ->formatStateUsing(fn (string $state): string => json_encode(json_decode($state), JSON_PRETTY_PRINT))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('whatsapp_session_id')
                    ->label('Filter by Customer Phone')
                    ->options(WhatsappSession::all()->pluck('customer_phone_number', 'id'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCartItems::route('/'),
            'create' => Pages\CreateCartItem::route('/create'),
            'edit' => Pages\EditCartItem::route('/{record}/edit'),
        ];
    }
}
