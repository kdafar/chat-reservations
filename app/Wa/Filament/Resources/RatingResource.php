<?php

namespace App\Wa\Filament\Resources;

use App\Wa\Filament\Resources\RatingResource\Pages;
use App\Wa\Hub\Models\Rating;
use Filament\Forms;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'WhatsApp Vendor Management';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rating Details')
                    ->schema([
                        Select::make('vendor_id')
                            ->label('Restaurant')
                            // IMPROVEMENT: More performant way to load options for large datasets
                            ->relationship('restaurant', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('whatsapp_session_id')
                            ->label('WhatsApp Session')
                            // IMPROVEMENT: More performant way to load options
                            ->relationship('whatsappSession', 'customer_phone_number')
                            ->searchable()
                            ->preload()
                            ->required(),

                        // IMPROVEMENT: Use a Radio component for a better UX than a text input
                        Radio::make('rating')
                            ->label('Rating')
                            ->options([
                                1 => '★☆☆☆☆ (Very Poor)',
                                2 => '★★☆☆☆ (Poor)',
                                3 => '★★★☆☆ (Average)',
                                4 => '★★★★☆ (Good)',
                                5 => '★★★★★ (Excellent)',
                            ])
                            ->required(),

                        // IMPROVEMENT: Add Order Number field for context
                        Forms\Components\TextInput::make('order_number')
                            ->label('Order Number')
                            ->required(),

                        Textarea::make('comment')
                            ->label('Comment')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // IMPROVEMENT: Add Order Number for context
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->searchable()
                    ->sortable(),
                // IMPROVEMENT: Use customer phone number from the rating record itself
                TextColumn::make('whatsapp_phone')
                    ->label('Customer Phone')
                    ->searchable()
                    ->sortable(),

                // IMPROVEMENT: Better visual display for the rating score
                IconColumn::make('rating')
                    ->label('Rating')
                    ->icon('heroicon-s-star')
                    ->color(fn (int $state): string => match ($state) {
                        1, 2 => 'danger',
                        3 => 'warning',
                        4, 5 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('comment')
                    ->label('Comment')
                    ->wrap()
                    ->limit(50) // Show a limited amount by default
                    ->toggleable(isToggledHiddenByDefault: false), // Show by default
                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('restaurant')
                    ->label('Filter by Restaurant')
                    ->relationship('restaurant', 'name')
                    ->searchable()
                    ->preload(),

                // CORRECTED: The proper way to create a date range filter
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date),
                            );
                    }),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRatings::route('/'),
            'create' => Pages\CreateRating::route('/create'),
            'edit' => Pages\EditRating::route('/{record}/edit'),
        ];
    }
}
