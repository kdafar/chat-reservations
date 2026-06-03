<?php

namespace App\Filament\Resources\Insurance\InsuranceClaimResource\RelationManagers;

use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\InsuranceClaimItem;
use App\Models\Insurance\InsuranceCoverageRule;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';

    protected static ?string $recordTitleAttribute = 'label';

    /**
     * Items are editable only when the claim is being decided (submitted or
     * under_review). In all other states the table is read-only.
     */
    protected function claimIsEditable(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof InsuranceClaim
            && in_array($owner->status, [
                InsuranceClaim::STATUS_SUBMITTED,
                InsuranceClaim::STATUS_UNDER_REVIEW,
            ], true);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('approved_amount')
                ->label('Approved Amount (KWD)')
                ->numeric()
                ->step(0.001)
                ->minValue(0)
                ->required()
                ->prefix('KWD'),

            Forms\Components\TextInput::make('patient_copay_amount')
                ->label('Patient Copay (KWD)')
                ->numeric()
                ->step(0.001)
                ->minValue(0)
                ->required()
                ->prefix('KWD'),

            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->maxLength(1000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        InsuranceCoverageRule::KIND_CONSULTATION => 'info',
                        InsuranceCoverageRule::KIND_SERVICES => 'success',
                        InsuranceCoverageRule::KIND_MEDICINES => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('label')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),

                Tables\Columns\TextColumn::make('unit_price_snapshot')
                    ->label('Unit Price')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),

                Tables\Columns\TextColumn::make('line_total')
                    ->label('Line Total')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),

                Tables\Columns\TextColumn::make('claimed_amount')
                    ->label('Claimed')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),

                Tables\Columns\TextColumn::make('approved_amount')
                    ->label('Approved')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),

                Tables\Columns\TextColumn::make('patient_copay_amount')
                    ->label('Copay')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (InsuranceClaimItem $r) => $this->claimIsEditable()),
            ])
            ->paginated(false);
    }
}
