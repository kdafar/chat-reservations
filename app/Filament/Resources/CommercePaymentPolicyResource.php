<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommercePaymentPolicyResource\Pages;
use App\Models\CommercePaymentPolicy;
use App\Models\Gateway;
use App\Models\GatewayAccount;
use Filament\Forms;
use Filament\Forms\Components as F;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns as C;

class CommercePaymentPolicyResource extends Resource
{
    protected static ?string $model = CommercePaymentPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'Payment Policies';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            F\Section::make('Basics')->schema([
                F\TextInput::make('name')->required()->maxLength(120),
                F\Toggle::make('is_enabled')->inline(false)->default(true),
                F\TextInput::make('priority')->numeric()->default(100)->helperText('Lower = higher priority'),
            ])->columns(3),

            F\Section::make('Scope (optional)')->schema([
                F\Select::make('partner_id')->relationship('partner', 'name')->searchable()->preload(),
                F\Select::make('service_id')->relationship('service', 'name')->searchable()->preload(),
                F\Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload(),
            ])->columns(3),

            F\Section::make('Conditions')->schema([
                F\Group::make()->statePath('conditions')->schema([
                    F\Select::make('currency')->label('Currency')->options([
                        'KWD' => 'KWD', 'USD' => 'USD', 'AED' => 'AED', 'SAR' => 'SAR',
                    ])->multiple()->helperText('Leave empty to allow any currency'),
                    F\CheckboxList::make('order_type')->label('Order Type')->options([
                        'delivery' => 'Delivery', 'pickup' => 'Pickup',
                    ])->columns(2),
                    F\Grid::make()->schema([
                        F\TextInput::make('min_total')->numeric()->label('Min Total (>=)'),
                        F\TextInput::make('max_total')->numeric()->label('Max Total (<)'),
                    ]),
                    F\CheckboxList::make('days_of_week')->label('Days of week')->options([
                        0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat',
                    ])->columns(7),
                    F\Grid::make()->schema([
                        F\TimePicker::make('time_between.0')->label('From')->seconds(false),
                        F\TimePicker::make('time_between.1')->label('To')->seconds(false),
                    ])->columns(2),
                ]),
            ])->collapsed(),

            // --- REFACTORED ACTION SECTION ---
            F\Section::make('Action')->schema([
                F\Radio::make('action_mode')
                    ->label('How to choose account?')
                    ->options(['explicit' => 'Use specific account', 'driver' => 'Choose by driver & owner preference'])
                    ->default('driver')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (Set $set) {
                        // When mode changes, clear all action fields to start fresh
                        $set('action.gateway_account_id', null);
                        $set('action.driver', null);
                        $set('action.owner_preference', null);
                        $set('action.allow_fallback', true);
                    })
                    ->afterStateHydrated(function (F\Radio $component, ?CommercePaymentPolicy $record) {
                        if (! $record || empty($record->action)) {
                            $component->state('driver');

                            return;
                        }
                        if (isset($record->action['gateway_account_id'])) {
                            $component->state('explicit');
                        } else {
                            $component->state('driver');
                        }
                    }),

                // Explicit Account (Uses dot notation for state path)
                F\Select::make('action.gateway_account_id')
                    ->label('Gateway Account')
                    ->options(fn () => GatewayAccount::query()->pluck('display_name', 'id'))
                    ->searchable()
                    ->required(fn (Get $get) => $get('action_mode') === 'explicit')
                    ->visible(fn (Get $get) => $get('action_mode') === 'explicit'),

                // Driver-based Routing (Uses dot notation for state path)
                F\Select::make('action.driver')
                    ->label('Driver')
                    ->options(fn () => Gateway::query()->where('is_active', true)->pluck('name', 'driver'))
                    ->required(fn (Get $get) => $get('action_mode') === 'driver')
                    ->visible(fn (Get $get) => $get('action_mode') === 'driver'),

                F\Repeater::make('action.owner_preference')
                    ->label('Owner preference order')
                    ->schema([
                        F\Select::make('owner')->options([
                            'branch' => 'Branch', 'partner' => 'Partner', 'service' => 'Service', 'system' => 'System',
                        ])->required(),
                    ])
                    ->default([['owner' => 'branch'], ['owner' => 'partner'], ['owner' => 'service'], ['owner' => 'system']])
                    ->columns(1)
                    ->visible(fn (Get $get) => $get('action_mode') === 'driver'),

                F\Toggle::make('action.allow_fallback')
                    ->default(true)
                    ->visible(fn (Get $get) => $get('action_mode') === 'driver'),
            ]),
        ]);
    }

    protected static function mutateFormDataBeforeSave(array $data): array
    {
        $action = [];
        // Reconstruct the 'action' JSON object from the flat dot-notated data.
        if (($data['action_mode'] ?? 'driver') === 'explicit') {
            $action['gateway_account_id'] = $data['action.gateway_account_id'] ?? null;
        } else {
            $action['driver'] = $data['action.driver'] ?? null;
            $action['owner_preference'] = $data['action.owner_preference'] ?? null;
            $action['allow_fallback'] = $data['action.allow_fallback'] ?? false;
        }

        // Clean up the temporary and flat keys from the final data array.
        unset($data['action_mode']);
        unset($data['action.gateway_account_id']);
        unset($data['action.driver']);
        unset($data['action.owner_preference']);
        unset($data['action.allow_fallback']);

        // Set the final, structured 'action' array.
        $data['action'] = $action;

        return $data;
    }

    // ... table() and getPages() methods remain the same ...
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                C\TextColumn::make('priority')->sortable(),
                C\IconColumn::make('is_enabled')->boolean(),
                C\TextColumn::make('name')->searchable()->wrap(),
                C\TextColumn::make('partner_id')->label('Partner')->state(fn (CommercePaymentPolicy $record) => optional($record->partner)->name)->toggleable(),
                C\TextColumn::make('service_id')->label('Service')->state(fn (CommercePaymentPolicy $record) => optional($record->service)->name)->toggleable(),
                C\TextColumn::make('branch_id')->label('Branch')->state(fn (CommercePaymentPolicy $record) => optional($record->branch)->name)->toggleable(),
                C\TextColumn::make('updated_at')->dateTime()->since()->sortable(),
            ])
            ->defaultSort('priority')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label('Enable/Disable')
                    ->action(fn (CommercePaymentPolicy $p) => $p->update(['is_enabled' => ! $p->is_enabled])),
                Tables\Actions\ReplicateAction::make()->label('Duplicate'),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommercePaymentPolicies::route('/'),
            'create' => Pages\CreateCommercePaymentPolicy::route('/create'),
            'edit' => Pages\EditCommercePaymentPolicy::route('/{record}/edit'),
        ];
    }
}
