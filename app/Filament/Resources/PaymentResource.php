<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\CommercePayment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = CommercePayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 22;

    public static function form(Form $form): Form
    {
        // no edit form — payments are system-managed
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.code')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'danger' => 'failed',
                        'warning' => 'pending',
                        'gray' => 'refunded',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('gatewayAccount.gateway.driver')
                    ->label('Driver')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount')
                    ->sortable()
                    ->alignRight()
                    // 👇 FIX IS HERE 👇
                    ->formatStateUsing(fn ($state, \App\Models\CommercePayment $record) => number_format((float) $state, 3).' '.($record->currency ?? 'KWD')),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                // ... filters
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('View'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
