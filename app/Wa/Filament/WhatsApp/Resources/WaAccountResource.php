<?php

namespace App\Wa\Filament\WhatsApp\Resources;

use App\Wa\Filament\WhatsApp\Resources\WaAccountResource\Pages;
use App\Wa\Models\User;
use App\Wa\Models\WhatsApp\WaAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaAccountResource extends Resource
{
    protected static ?string $model = WaAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?string $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0'); // safety: no user => no records
        }

        $isAdmin = method_exists($user, 'hasRole')
            ? $user->hasRole('admin')
            : ($user->is_admin ?? false);

        if (! $isAdmin) {
            $query->where('owner_user_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Account Name')
                        ->required(),

                    Forms\Components\TextInput::make('external_business_id')
                        ->label('WABA ID (external_business_id)')
                        ->helperText('WhatsApp Business Account ID from Meta')
                        ->nullable(),

                    Forms\Components\Select::make('owner_user_id')
                        ->label('Owner (portal user)')
                        ->options(function () {
                            $user = auth()->user();
                            $isAdmin = method_exists($user, 'hasRole')
                                ? $user->hasRole('admin')
                                : ($user->is_admin ?? false);

                            return User::query()
                                ->when(! $isAdmin, fn ($q) => $q->where('id', $user->id))
                                ->select('id', 'name', 'email')
                                ->get()
                                ->mapWithKeys(fn (User $u) => [
                                    $u->id => $u->name ?: ($u->email ?: ('User #'.$u->id)),
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->default(fn () => auth()->id())
                        ->required(),

                    // Forms\Components\TextInput::make('customer_id')
                    //     ->label('Internal customer/site ID')
                    //     ->nullable(),

                    Forms\Components\TextInput::make('timezone')
                        ->default('Asia/Kuwait')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'paused' => 'Paused',
                            'disconnected' => 'Disconnected',
                        ])
                        ->default('active')
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Meta data')
                ->schema([
                    Forms\Components\Textarea::make('meta_raw')
                        ->label('Raw Meta payload (JSON)')
                        ->rows(5)
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Synced from onboarding / Graph API.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Account')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('external_business_id')
                    ->label('WABA ID')
                    ->toggleable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('numbers_count')
                    ->counts('numbers')
                    ->label('Numbers'),

                Tables\Columns\TextColumn::make('timezone')
                    ->label('TZ')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'disconnected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->label('Created')
                    ->sortable(),
            ])
            ->actions([
                // 🔴 Detach action
                Tables\Actions\Action::make('detach')
                    ->label('Detach from platform')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Detach this WhatsApp account?')
                    ->modalDescription('We will disconnect all numbers, clear credentials, and stop sending messages for this account. You can reconnect later via Embedded Signup.')
                    ->action(function (WaAccount $record) {
                        // 1) Mark account as disconnected
                        $record->status = 'disconnected';
                        $meta = $record->meta_raw ?? [];
                        $meta['detached_at'] = now()->toIso8601String();
                        $meta['detached_by_user_id'] = auth()->id();
                        $record->meta_raw = $meta;
                        $record->save();

                        // 2) Clear credentials tokens & mark them expired
                        foreach ($record->credentials as $cred) {
                            $cred->token = null;
                            $meta = $cred->meta_raw ?? [];
                            $meta['detached_at'] = now()->toIso8601String();
                            $cred->meta_raw = $meta;
                            $cred->expires_at = now();
                            $cred->save();
                        }

                        // 3) Disconnect all numbers + unlink credential
                        foreach ($record->numbers as $number) {
                            $number->update([
                                'status' => 'disconnected',
                                'credential_id' => null,
                            ]);
                        }

                        Notification::make()
                            ->title('Account detached')
                            ->body('All numbers were disconnected and credentials cleared. No further messages will be sent for this account.')
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
            'index' => Pages\ListWaAccounts::route('/'),
            'create' => Pages\CreateWaAccount::route('/create'),
            'edit' => Pages\EditWaAccount::route('/{record}/edit'),
        ];
    }
}
