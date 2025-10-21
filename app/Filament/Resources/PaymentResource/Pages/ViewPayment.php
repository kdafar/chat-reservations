<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Payment')
                ->schema([
                    TextEntry::make('order.code')->label('Order #'),
                    TextEntry::make('method')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('gatewayAccount.gateway.name')->label('Gateway')->placeholder('—'),
                    TextEntry::make('gatewayAccount.gateway.driver')->label('Driver')->placeholder('—'),
                    TextEntry::make('amount')->label('Amount')
                        // 👇 FIX IS HERE 👇
                        ->formatStateUsing(fn ($state, \App\Models\CommercePayment $record) => number_format((float) $state, 3).' '.($record->currency ?? 'KWD')),
                    TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                    TextEntry::make('transaction_id')->label('Transaction #')->placeholder('—'),
                    TextEntry::make('provider_payment_id')->label('Provider Payment ID')->placeholder('—'),
                ])->columns(3),

            Section::make('Raw Payload')->collapsible()->collapsed()
                ->schema([
                    TextEntry::make('provider_payload')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->copyable()
                        ->columnSpanFull()
                        ->hint('Copied payload will be raw JSON.'),
                ]),
        ]);
    }
}
