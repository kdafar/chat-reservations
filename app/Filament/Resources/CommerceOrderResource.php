<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceOrderResource\Pages;
use App\Models\CommerceOrder;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommerceOrderResource extends Resource
{
    protected static ?string $model = CommerceOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 5;

    /** Scope: show only orders for branches linked to the user (admins see all). */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->hasRole('admin')) {
            return $query;
        }

        // user->branchLinks() is the belongsToMany on branch_user pivot we added earlier
        $branchIds = $user?->branchLinks()->pluck('branches.id')->all() ?? [];

        // If user has no branches, show nothing
        if (empty($branchIds)) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }

    /** We don't need a form (no create/edit) */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Order #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->wrap(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'secondary' => 'draft',
                        'primary' => 'placed',
                        'warning' => 'confirmed',
                        'info' => 'preparing',
                        'success' => 'delivered',
                        'gray' => 'ready',
                        'gray-500' => 'out_for_delivery',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 3).' '.($record->currency ?? 'KWD'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Placed At')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'placed' => 'Placed',
                    'confirmed' => 'Confirmed',
                    'preparing' => 'Preparing',
                    'ready' => 'Ready',
                    'out_for_delivery' => 'Out for Delivery',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                    'rejected' => 'Rejected',
                ]),
                Tables\Filters\SelectFilter::make('type')->options([
                    'delivery' => 'Delivery',
                    'pickup' => 'Pickup',
                ]),
            ])
            ->actions([
                // Quick status actions with simple guards
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->visible(fn (CommerceOrder $r) => $r->status === 'placed')
                    ->action(fn (CommerceOrder $r) => $r->update(['status' => 'confirmed', 'confirmed_at' => now()])),

                Tables\Actions\Action::make('preparing')
                    ->label('Preparing')
                    ->visible(fn (CommerceOrder $r) => $r->status === 'confirmed')
                    ->action(fn (CommerceOrder $r) => $r->update(['status' => 'preparing'])),

                Tables\Actions\Action::make('ready')
                    ->label('Ready')
                    ->visible(fn (CommerceOrder $r) => $r->status === 'preparing')
                    ->action(fn (CommerceOrder $r) => $r->update(['status' => 'ready'])),

                Tables\Actions\Action::make('out_for_delivery')
                    ->label('Out for Delivery')
                    ->visible(fn (CommerceOrder $r) => $r->status === 'ready')
                    ->action(fn (CommerceOrder $r) => $r->update(['status' => 'out_for_delivery'])),

                Tables\Actions\Action::make('delivered')
                    ->label('Delivered')
                    ->color('success')
                    ->visible(fn (CommerceOrder $r) => in_array($r->status, ['out_for_delivery', 'ready']))
                    ->action(fn (CommerceOrder $r) => $r->update(['status' => 'delivered', 'delivered_at' => now()])),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CommerceOrder $r) => in_array($r->status, ['placed', 'confirmed', 'preparing', 'ready']))
                    ->action(fn (CommerceOrder $r) => $r->update(['status' => 'cancelled'])),

                Tables\Actions\ViewAction::make(), // optional view
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(), // usually disabled for orders; keep/remove as you prefer
            ]);
    }

    public static function getRelations(): array
    {
        return []; // add relation managers (items) later if you want
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Order')
                ->schema([
                    TextEntry::make('code')->label('Order #'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('type')->badge(),
                    TextEntry::make('service.name')->label('Service'),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('currency')->label('Currency'),
                    TextEntry::make('items_total')->label('Items Total')
                        // 👇 Add CommerceOrder type hint
                        ->formatStateUsing(fn ($state, CommerceOrder $r) => number_format((float) $state, 3).' '.($r->currency ?? 'KWD')),
                    TextEntry::make('delivery_fee')->label('Delivery Fee')
                        // 👇 Add CommerceOrder type hint
                        ->formatStateUsing(fn ($state, CommerceOrder $r) => number_format((float) $state, 3).' '.($r->currency ?? 'KWD')),
                    TextEntry::make('grand_total')->label('Grand Total')
                        ->weight('bold')
                        // 👇 Add CommerceOrder type hint
                        ->formatStateUsing(fn ($state, CommerceOrder $r) => number_format((float) $state, 3).' '.($r->currency ?? 'KWD')),
                    TextEntry::make('placed_at')->dateTime()->label('Placed At'),
                    // 👇 Add CommerceOrder type hint
                    TextEntry::make('confirmed_at')->dateTime()->label('Confirmed At')->hidden(fn (CommerceOrder $r) => ! $r->confirmed_at),
                    // 👇 Add CommerceOrder type hint
                    TextEntry::make('delivered_at')->dateTime()->label('Delivered At')->hidden(fn (CommerceOrder $r) => ! $r->delivered_at),
                    TextEntry::make('notes')->columnSpanFull()->hidden(fn ($state) => empty($state)),
                ])
                ->columns(3),

            Section::make('Payment')
                ->schema([
                    TextEntry::make('payment.method')->label('Method')->placeholder('—'),
                    TextEntry::make('payment.status')->label('Status')->badge()->placeholder('—'),
                    TextEntry::make('payment.paid_at')->dateTime()->label('Paid At')->placeholder('—'),
                    TextEntry::make('payment.transaction_id')->label('Transaction #')->placeholder('—'),
                    TextEntry::make('payment.provider_payment_id')->label('Provider Payment ID')->placeholder('—'),
                ])
                ->columns(3),

            Section::make('Items')
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->state(function (CommerceOrder $record) {
                            return $record->items()
                                ->with('modifiers')
                                ->get()
                                ->map(function ($i) {
                                    return [
                                        'name' => $i->name,
                                        'sku' => $i->sku,
                                        'quantity' => $i->quantity,
                                        'unit_price' => (float) $i->unit_price,
                                        'subtotal' => (float) $i->subtotal,
                                        'modifiers' => $i->modifiers->map(fn ($m) => [
                                            'group_name' => $m->group_name,
                                            'option_name' => $m->option_name,
                                            'price_delta' => (float) $m->price_delta,
                                        ])->all(),
                                    ];
                                })
                                ->all();
                        })
                        ->schema([
                            TextEntry::make('name')->label('Item'),
                            TextEntry::make('sku')->label('SKU')->placeholder('—'),
                            TextEntry::make('quantity')->label('Qty'),
                            TextEntry::make('unit_price')
                                ->label('Unit')
                                ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),
                            TextEntry::make('subtotal')
                                ->label('Subtotal')
                                ->weight('medium')
                                ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),
                            RepeatableEntry::make('modifiers')
                                ->label('Modifiers')
                                ->schema([
                                    TextEntry::make('group_name')->label('Group'),
                                    TextEntry::make('option_name')->label('Option'),
                                    TextEntry::make('price_delta')
                                        ->label('Δ Price')
                                        ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),
                                ])
                                ->columns(3)
                                ->visible(fn ($state) => is_array($state) && count($state) > 0),
                        ])
                        ->columns(5)
                        ->grid(1),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommerceOrders::route('/'),
            'view' => Pages\ViewCommerceOrder::route('/{record}'),
        ];
    }
}
