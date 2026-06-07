<?php

namespace App\Wa\Filament\Resources;

use App\Wa\Filament\Resources\WhatsappSessionResource\Pages;
use App\Wa\Hub\Models\Vendors;
use App\Wa\Hub\Models\WhatsappSession; // Import Restaurant
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

class WhatsappSessionResource extends Resource
{
    protected static ?string $model = WhatsappSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?int $navigationSort = 8;

    // Group under "WhatsApp Vendor Management"
    protected static ?string $navigationGroup = 'WhatsApp Vendor Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('WhatsApp Session Details')
                    ->schema([
                        TextInput::make('customer_phone_number')
                            ->label('Customer Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                'pending' => 'Pending',
                                // Add more statuses as per your application logic
                            ])
                            ->required(),
                        Select::make('selected_vendor_id')
                            ->label('Selected Restaurant')
                            ->options(Vendors::all()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_phone_number')
                    ->label('Customer Phone Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge() // Adds a nice badge styling
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('restaurant.name')
                    ->label('Selected Restaurant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'pending' => 'Pending',
                    ])
                    ->label('Filter by Status'),
                SelectFilter::make('selected_vendor_id')
                    ->label('Filter by Restaurant')
                    ->options(Vendors::all()->pluck('name', 'id'))
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
            // You might want to add relations here later if you want to show
            // CartItems or Ratings directly within the WhatsappSession edit page
            // Forms\Components\Tabs\Tab::make('Cart Items')
            //     ->schema([
            //         // Filament table for CartItems related to this session
            //     ]),
            // Forms\Components\Tabs\Tab::make('Ratings')
            //     ->schema([
            //         // Filament table for Ratings related to this session
            //     ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsappSessions::route('/'),
            'create' => Pages\CreateWhatsappSession::route('/create'),
            'edit' => Pages\EditWhatsappSession::route('/{record}/edit'),
        ];
    }
}
