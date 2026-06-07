<?php

namespace App\Wa\Filament\WhatsApp\Resources;

use App\Wa\Filament\WhatsApp\Resources\WaNumberResource\Pages;
use App\Wa\Models\WhatsApp\WaNumber;
use App\Wa\Services\WhatsApp\Tenant\TenantWhatsAppService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaNumberResource extends Resource
{
    protected static ?string $model = WaNumber::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';

    protected static ?string $navigationLabel = 'WhatsApp Numbers';

    protected static ?string $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 11;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0'); // safety
        }

        $isAdmin = method_exists($user, 'hasRole')
            ? $user->hasRole('admin')
            : ($user->is_admin ?? false);

        if (! $isAdmin) {
            // Only numbers whose account belongs to this user
            $query->whereHas('account', function (Builder $q) use ($user) {
                $q->where('owner_user_id', $user->id);
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Number')
                    ->schema([
                        Forms\Components\Select::make('wa_account_id')
                            ->relationship('account', 'name')
                            ->label('Account')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('display_phone_number')
                            ->label('Display Phone Number')
                            ->placeholder('+965 559 07578')
                            ->required(),

                        Forms\Components\TextInput::make('phone_number_id')
                            ->label('Phone Number ID (Meta)')
                            ->required()
                            ->helperText('From Cloud API → phone numbers page'),

                        Forms\Components\TextInput::make('verified_name')
                            ->label('Verified Name')
                            ->placeholder('Zad By Majestic'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Health & Limits')
                    ->schema([
                        Forms\Components\TextInput::make('quality_rating')
                            ->helperText('GREEN / YELLOW / RED')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('messaging_limit_tier')
                            ->helperText('e.g. 1K per 24h')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('account_mode')
                            ->helperText('live / test')
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->options([
                                'connected' => 'Connected',
                                'disconnected' => 'Disconnected',
                                'error' => 'Error',
                            ])
                            ->default('connected')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('display_phone_number')
                    ->label('Phone')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('verified_name')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('quality_rating')
                    ->colors([
                        'success' => 'GREEN',
                        'warning' => 'YELLOW',
                        'danger' => 'RED',
                    ]),
                Tables\Columns\TextColumn::make('messaging_limit_tier')
                    ->label('Limit / 24h'),
                Tables\Columns\TextColumn::make('account_mode')
                    ->label('Mode'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'connected',
                        'danger' => 'disconnected',
                        'warning' => 'error',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->label('Created')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync from Meta')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (WaNumber $record) {
                        try {
                            app(TenantWhatsAppService::class)->syncPhoneInfo($record);

                            Notification::make()
                                ->title('Number synchronized')
                                ->body('Latest info fetched from Meta.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('disconnect')
                    ->label('Disconnect')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (WaNumber $record) {
                        /** Here we only update DB. Later we’ll also call an API to unsubscribe. */
                        $record->update(['status' => 'disconnected']);

                        Notification::make()
                            ->title('Number disconnected')
                            ->body('The number has been marked as disconnected.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaNumbers::route('/'),
            'create' => Pages\CreateWaNumber::route('/create'),
            'edit' => Pages\EditWaNumber::route('/{record}/edit'),
        ];
    }
}
