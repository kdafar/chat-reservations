<?php

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Models\ClinicItem;
use App\Models\VisitItem;
use App\Services\Clinic\VisitCostingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'visitItems';

    protected static ?string $title = 'Items Used';

    protected static function financialsEnabled(): bool
    {
        return (bool) config('clinic.visit_financials_enabled', false);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('clinic_item_id')
                ->label('Clinic Item')
                ->searchable()
                ->preload()
                ->required()
                ->options(function () {
                    $visit = $this->getOwnerRecord();
                    $branchId = (int) ($visit->branch_id ?? 0);

                    return ClinicItem::query()
                        ->where('is_active', 1)
                        ->where(function (Builder $q) use ($branchId) {
                            $q->whereNull('branch_id');
                            if ($branchId) {
                                $q->orWhere('branch_id', $branchId);
                            }
                        })
                        ->orderBy('id', 'desc')
                        ->get()
                        ->mapWithKeys(fn (ClinicItem $it) => [$it->id => $it->localized_name])
                        ->all();
                })
                ->reactive()
                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                    if (! $state) {
                        return;
                    }

                    $item = ClinicItem::query()->find($state);
                    if (! $item) {
                        return;
                    }

                    // Snapshot defaults at selection time (audit-proof)
                    $set('unit_cost_snapshot', (string) ($item->default_cost ?? 0));
                    $set('unit_price_snapshot', (string) ($item->default_price ?? 0));

                    // Recompute totals using current qty + snapshots
                    $totals = self::computeTotals($get);
                    $set('line_cost_total', (string) $totals['line_cost_total']);
                    $set('line_price_total', (string) $totals['line_price_total']);
                }),

            Forms\Components\TextInput::make('qty')
                ->label('Qty')
                ->numeric()
                ->step('0.001')
                ->default(1)
                ->required()
                ->reactive()
                ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set) {
                    $totals = self::computeTotals($get);
                    $set('line_cost_total', (string) $totals['line_cost_total']);
                    $set('line_price_total', (string) $totals['line_price_total']);
                })
                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                    $totals = self::computeTotals($get);
                    $set('line_cost_total', (string) $totals['line_cost_total']);
                    $set('line_price_total', (string) $totals['line_price_total']);
                }),

            Forms\Components\TextInput::make('unit_cost_snapshot')
                ->label('Unit Cost (Snapshot)')
                ->numeric()
                ->step('0.001')
                ->default(0)
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                    $totals = self::computeTotals($get);
                    $set('line_cost_total', (string) $totals['line_cost_total']);
                    $set('line_price_total', (string) $totals['line_price_total']);
                }),

            Forms\Components\TextInput::make('unit_price_snapshot')
                ->label('Unit Price (Snapshot)')
                ->numeric()
                ->step('0.001')
                ->default(0)
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                    $totals = self::computeTotals($get);
                    $set('line_cost_total', (string) $totals['line_cost_total']);
                    $set('line_price_total', (string) $totals['line_price_total']);
                }),

            Forms\Components\TextInput::make('line_cost_total')
                ->label('Line Cost Total')
                ->numeric()
                ->step('0.001')
                ->default(0)
                ->disabled()
                ->dehydrated(true),

            Forms\Components\TextInput::make('line_price_total')
                ->label('Line Price Total')
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
                Tables\Columns\TextColumn::make('clinicItem.name')
                    ->label('Item')
                    ->formatStateUsing(fn ($state, VisitItem $r) => $r->clinicItem?->localized_name ?? ('#'.$r->clinic_item_id))
                    ->searchable(),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric(3),

                Tables\Columns\TextColumn::make('unit_cost_snapshot')
                    ->label('Unit Cost')
                    ->numeric(3),

                Tables\Columns\TextColumn::make('unit_price_snapshot')
                    ->label('Unit Price')
                    ->numeric(3),

                Tables\Columns\TextColumn::make('line_cost_total')
                    ->label('Line Cost')
                    ->numeric(3),

                Tables\Columns\TextColumn::make('line_price_total')
                    ->label('Line Price')
                    ->numeric(3),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $visit = $this->getOwnerRecord();

                        // Snapshot branch from visit
                        $data['branch_id'] = $visit->branch_id ?? null;

                        // Ensure line totals are consistent even if reactive hooks didn’t run
                        $qty = (float) ($data['qty'] ?? 0);
                        $unitCost = (float) ($data['unit_cost_snapshot'] ?? 0);
                        $unitPrice = (float) ($data['unit_price_snapshot'] ?? 0);

                        $data['line_cost_total'] = $qty * $unitCost;
                        $data['line_price_total'] = $qty * $unitPrice;

                        return $data;
                    })
                    ->after(function () {
                        $this->recomputeVisitFinancials();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $visit = $this->getOwnerRecord();
                        $data['branch_id'] = $visit->branch_id ?? ($data['branch_id'] ?? null);

                        $qty = (float) ($data['qty'] ?? 0);
                        $unitCost = (float) ($data['unit_cost_snapshot'] ?? 0);
                        $unitPrice = (float) ($data['unit_price_snapshot'] ?? 0);

                        $data['line_cost_total'] = $qty * $unitCost;
                        $data['line_price_total'] = $qty * $unitPrice;

                        return $data;
                    })
                    ->after(function () {
                        $this->recomputeVisitFinancials();
                    }),

                Tables\Actions\DeleteAction::make()
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

    protected static function computeTotals(Forms\Get $get): array
    {
        $qty = (float) ($get('qty') ?? 0);
        $unitCost = (float) ($get('unit_cost_snapshot') ?? 0);
        $unitPrice = (float) ($get('unit_price_snapshot') ?? 0);

        return [
            'line_cost_total' => $qty * $unitCost,
            'line_price_total' => $qty * $unitPrice,
        ];
    }
}
