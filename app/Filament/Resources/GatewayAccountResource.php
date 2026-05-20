<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GatewayAccountResource\Pages;
use App\Models\Gateway;
use App\Models\GatewayAccount;
use Filament\Forms;
use Filament\Forms\Components as F;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns as C;

class GatewayAccountResource extends Resource
{
    protected static ?string $model = GatewayAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Clinic — Setup';

    protected static ?string $navigationLabel = 'Gateway Accounts';

    protected static ?int $navigationSort = 40;

    /**
     * We treat these as "manual methods" that drive your Booking payment select.
     * Stored as credentials.method
     */
    protected static function manualMethods(): array
    {
        return [
            'cash' => 'Cash',
            'knet' => 'KNET (POS)',
            'visa' => 'Credit Card (POS)',
            'link' => 'Payment Link (Online)',
        ];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            F\Section::make('Type')->schema([
                /**
                 * New: record kind selector (manual vs gateway)
                 * - Manual: credentials.method drives the Booking dropdown options.
                 * - Gateway: gateway_id + credentials are for real gateways like MyFatoorah.
                 *
                 * This is add-only and does not change DB schema; we store it in credentials.kind.
                 */
                F\Radio::make('credentials.kind')
                    ->label('Account kind')
                    ->options([
                        'manual' => 'Manual / POS method',
                        'gateway' => 'Online gateway account',
                    ])
                    ->default(fn (?GatewayAccount $record) => data_get($record?->credentials, 'kind', 'gateway'))
                    ->inline()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        // If switching to manual, ensure method key exists (default knet)
                        if ($state === 'manual') {
                            $set('credentials.method', $setValue = data_get(static::manualMethods(), 'knet') ? 'knet' : 'cash');
                        }
                    }),
            ])->columns(1),

            F\Section::make('Gateway / Method')->schema([
                // Manual method select (stored in credentials.method)
                F\Select::make('credentials.method')
                    ->label('Manual payment method')
                    ->options(static::manualMethods())
                    ->searchable()
                    ->required(fn (Get $get) => (string) $get('credentials.kind') === 'manual')
                    ->visible(fn (Get $get) => (string) $get('credentials.kind') === 'manual')
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                        // Auto-fill display name if empty (non-destructive)
                        $current = (string) ($get('display_name') ?? '');
                        if ($current === '' && $state) {
                            $label = static::manualMethods()[$state] ?? ucfirst($state);
                            $set('display_name', $label);
                        }
                    }),

                // Online gateway select (required only for gateway-kind)
                F\Select::make('gateway_id')
                    ->label('Gateway')
                    ->options(fn () => Gateway::query()->pluck('name', 'id'))
                    ->searchable()
                    ->required(fn (Get $get) => (string) $get('credentials.kind') !== 'manual')
                    ->visible(fn (Get $get) => (string) $get('credentials.kind') !== 'manual'),

                F\TextInput::make('display_name')
                    ->required()
                    ->maxLength(120),

                /**
                 * Credentials editor:
                 * - For manual records: we mostly care about method/kind, but keep this available.
                 * - For gateway records: store api_key/mode/country_iso, etc.
                 */
                F\KeyValue::make('credentials')
                    ->label('Credentials (JSON)')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->reorderable()
                    ->helperText(function (Get $get) {
                        if ((string) $get('credentials.kind') === 'manual') {
                            return 'For manual methods: set credentials.method (cash/knet/visa/link). Other keys are optional.';
                        }

                        return 'For gateways: store keys like api_key, mode, country_iso, etc.';
                    }),

                F\TextInput::make('currency')
                    ->default('KWD')
                    ->required(),

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
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        // Reduce accidental stale IDs when switching owner_type (model also normalizes on saving)
                        if ($state !== 'partner') {
                            $set('partner_id', null);
                        }
                        if ($state !== 'branch') {
                            $set('branch_id', null);
                        }
                        if ($state !== 'service') {
                            $set('service_id', null);
                        }
                    }),

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

                // Show kind (manual/gateway) derived from credentials.kind
                C\BadgeColumn::make('kind')
                    ->label('Kind')
                    ->state(fn (GatewayAccount $r) => (string) data_get($r->credentials, 'kind', 'gateway'))
                    ->colors([
                        'success' => 'manual',
                        'primary' => 'gateway',
                    ])
                    ->toggleable(),

                // Show method for manual records
                C\TextColumn::make('method')
                    ->label('Method')
                    ->state(function (GatewayAccount $r) {
                        $kind = (string) data_get($r->credentials, 'kind', 'gateway');
                        if ($kind !== 'manual') {
                            return '—';
                        }

                        $m = (string) data_get($r->credentials, 'method', '');

                        return $m !== '' ? $m : '—';
                    })
                    ->toggleable(),

                // Gateway name (only meaningful for gateway-kind)
                C\TextColumn::make('gateway.name')
                    ->label('Gateway')
                    ->state(fn (GatewayAccount $r) => $r->gateway?->label() ?? ($r->gateway?->driver ?? '—'))
                    ->sortable()
                    ->toggleable(),

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

                Tables\Filters\SelectFilter::make('kind')
                    ->label('Kind')
                    ->options(['manual' => 'Manual / POS', 'gateway' => 'Gateway'])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        // credentials.kind stored in JSON
                        return $query->where('credentials->kind', $value);
                    }),

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
