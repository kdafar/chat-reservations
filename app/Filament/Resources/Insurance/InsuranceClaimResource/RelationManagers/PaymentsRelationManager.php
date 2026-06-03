<?php

namespace App\Filament\Resources\Insurance\InsuranceClaimResource\RelationManagers;

use App\Models\Insurance\InsuranceClaimPayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount (KWD)')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->weight('semibold')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->sortable(),

                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        InsuranceClaimPayment::METHOD_TRANSFER => 'info',
                        InsuranceClaimPayment::METHOD_CHEQUE => 'warning',
                        InsuranceClaimPayment::METHOD_CASH => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Reference')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->copyable(),

                Tables\Columns\TextColumn::make('depositedToAccount.code')
                    ->label('Account')
                    ->fontFamily('mono')
                    ->description(fn (InsuranceClaimPayment $r) => $r->depositedToAccount?->name)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('received_by_user_id')
                    ->label('Received By')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state
                        ? optional(\App\Models\User::find($state))->name ?? '#'.$state
                        : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('notes')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                // No create button — use parent claim's "Record Payment" action so the
                // accounting JE is posted via InsuranceService.
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Deleting a payment will reverse the matching journal entry (handled by the model observer).')
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false),
            ])
            ->paginated(false);
    }
}
