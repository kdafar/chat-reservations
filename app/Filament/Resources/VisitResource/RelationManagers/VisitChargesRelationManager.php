<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\VisitCharge;
use App\Services\Clinic\VisitCostingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VisitChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'visitCharges';

    protected static ?string $title = 'Charges & Fees';

    protected static ?string $recordTitleAttribute = 'label';

    protected static function financialsEnabled(): bool
    {
        return (bool) config('clinic.visit_financials_enabled', false);
    }

    protected static function canOverride(): bool
    {
        return (bool) (auth()->user()?->can('clinic_financial_override'));
    }

    public function form(Form $form): Form
    {
        $canOverride = static::canOverride();

        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->required()
                ->maxLength(255)
                ->disabled(! $canOverride)
                ->dehydrated(),

            Forms\Components\TextInput::make('qty')
                ->label('Qty')
                ->numeric()
                ->step('0.001')
                ->minValue(0)
                ->default(1)
                ->required()
                ->reactive()
                ->disabled(! $canOverride)
                ->dehydrated()
                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                    $set('line_total', (string) self::computeLineTotal($get));
                }),

            Forms\Components\TextInput::make('unit_price_snapshot')
                ->label('Unit Price')
                ->numeric()
                ->step('0.001')
                ->minValue(0)
                ->default(0)
                ->required()
                ->suffix('KWD')
                ->reactive()
                ->disabled(! $canOverride)
                ->dehydrated()
                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                    $set('line_total', (string) self::computeLineTotal($get));
                }),

            Forms\Components\TextInput::make('discount_amount')
                ->label('Line Discount')
                ->numeric()
                ->step('0.001')
                ->minValue(0)
                ->default(0)
                ->suffix('KWD')
                ->helperText('Per-line discount, subtracted from this charge\'s gross. Stacks under Visit-level discount.'),

            Forms\Components\TextInput::make('line_total')
                ->label('Line Total')
                ->numeric()
                ->step('0.001')
                ->default(0)
                ->disabled()
                ->dehydrated(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Charge')
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric(3),

                Tables\Columns\TextColumn::make('unit_price_snapshot')
                    ->label('Unit Price')
                    ->numeric(3),

                Tables\Columns\TextColumn::make('line_total')
                    ->label('Gross')
                    ->numeric(3),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('net_total')
                    ->label('Net')
                    ->getStateUsing(fn (VisitCharge $r) => $r->net_total)
                    ->numeric(3),

                Tables\Columns\TextColumn::make('addedBy.name')
                    ->label('Added By')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d h:i A')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Charge')
                    ->visible(fn (): bool => static::canOverride())
                    ->mutateFormDataUsing(function (array $data): array {
                        $visit = $this->getOwnerRecord();
                        $data['visit_id'] = $visit->id;
                        $data['branch_id'] = $visit->branch_id ?? null;
                        $data['added_by_user_id'] = (int) (auth()->id() ?? 0) ?: null;

                        $qty = (float) ($data['qty'] ?? 0);
                        $unitPrice = (float) ($data['unit_price_snapshot'] ?? 0);
                        $data['line_total'] = round($qty * $unitPrice, 3);

                        return $data;
                    })
                    ->after(function () {
                        $this->recomputeVisitFinancials();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, VisitCharge $record): array {
                        if (static::canOverride()) {
                            $qty = (float) ($data['qty'] ?? 0);
                            $unitPrice = (float) ($data['unit_price_snapshot'] ?? 0);
                            $data['line_total'] = round($qty * $unitPrice, 3);
                        } else {
                            // Non-override users can only set discount — keep snapshot fields intact.
                            $data['label'] = $record->label;
                            $data['qty'] = $record->qty;
                            $data['unit_price_snapshot'] = $record->unit_price_snapshot;
                            $data['line_total'] = $record->line_total;
                        }

                        return $data;
                    })
                    ->after(function () {
                        $this->recomputeVisitFinancials();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => static::canOverride())
                    ->after(function () {
                        $this->recomputeVisitFinancials();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    protected function recomputeVisitFinancials(): void
    {
        if (! static::financialsEnabled()) {
            return;
        }

        $visit = $this->getOwnerRecord();

        try {
            app(VisitCostingService::class)->compute($visit, (int) (auth()->id() ?? 0));

            Notification::make()
                ->title('Visit financial snapshot updated')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to recompute visit financials')
                ->body('Please check logs and try again.')
                ->danger()
                ->send();
        }
    }

    protected static function computeLineTotal(Forms\Get $get): float
    {
        $qty = (float) ($get('qty') ?? 0);
        $unitPrice = (float) ($get('unit_price_snapshot') ?? 0);

        return round($qty * $unitPrice, 3);
    }
}
