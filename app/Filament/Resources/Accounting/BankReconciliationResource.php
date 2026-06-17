<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\BankReconciliationResource\Pages;
use App\Filament\Resources\Accounting\BankReconciliationResource\RelationManagers\StatementLinesRelationManager;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliation;
use App\Services\Accounting\BankReconciliationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankReconciliationResource extends Resource
{
    protected static ?string $model = BankReconciliation::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'accounting/bank-reconciliations';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.bank_reconciliation.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.bank_reconciliation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.bank_reconciliation.label_plural');
    }

    public static function form(Form $form): Form
    {
        $lockedOnEdit = fn (?BankReconciliation $record): bool => $record !== null
            && $record->status !== BankReconciliation::STATUS_IN_PROGRESS;

        return $form->schema([
            Forms\Components\Section::make(__('accounting.bank_reconciliation.section_reconciliation'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('account_id')
                        ->label(__('accounting.bank_reconciliation.bank_cash_account'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->disabled($lockedOnEdit)
                        ->options(fn () => self::bankAccountOptions())
                        ->helperText(__('accounting.bank_reconciliation.bank_cash_helper')),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('period_start')
                            ->label(__('accounting.bank_reconciliation.period_start'))
                            ->required()
                            ->native(false)
                            ->disabled($lockedOnEdit),

                        Forms\Components\DatePicker::make('period_end')
                            ->label(__('accounting.bank_reconciliation.period_end'))
                            ->required()
                            ->native(false)
                            ->afterOrEqual('period_start')
                            ->disabled($lockedOnEdit),
                    ])->columnSpan(1),

                    Forms\Components\TextInput::make('opening_balance')
                        ->label(__('accounting.bank_reconciliation.opening_balance_statement'))
                        ->numeric()
                        ->step(0.001)
                        ->required()
                        ->default(0)
                        ->prefix('KWD')
                        ->disabled($lockedOnEdit),

                    Forms\Components\TextInput::make('closing_balance')
                        ->label(__('accounting.bank_reconciliation.closing_balance_statement'))
                        ->numeric()
                        ->step(0.001)
                        ->required()
                        ->default(0)
                        ->prefix('KWD')
                        ->disabled($lockedOnEdit),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('accounting.bank_reconciliation.notes'))
                        ->rows(3)
                        ->columnSpanFull()
                        ->disabled($lockedOnEdit),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('accounting.bank_reconciliation.code'))
                    ->searchable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('account.code')
                    ->label(__('accounting.bank_reconciliation.account'))
                    ->fontFamily('mono')
                    ->description(fn (BankReconciliation $r) => $r->account?->name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('period_range')
                    ->label(__('accounting.bank_reconciliation.period'))
                    ->getStateUsing(fn (BankReconciliation $r) => $r->period_start?->format('Y-m-d')
                        .' → '.$r->period_end?->format('Y-m-d'))
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('opening_balance')
                    ->label(__('accounting.bank_reconciliation.opening'))
                    ->numeric(3)
                    ->alignEnd()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('closing_balance')
                    ->label(__('accounting.bank_reconciliation.closing'))
                    ->numeric(3)
                    ->alignEnd()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('book_opening_balance')
                    ->label(__('accounting.bank_reconciliation.book_opening'))
                    ->numeric(3)
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('book_closing_balance')
                    ->label(__('accounting.bank_reconciliation.book_closing'))
                    ->numeric(3)
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('diff')
                    ->label(__('accounting.bank_reconciliation.diff'))
                    ->getStateUsing(function (BankReconciliation $r) {
                        $diff = (float) $r->closing_balance - (float) $r->book_closing_balance;

                        return number_format($diff, 3);
                    })
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->color(function (BankReconciliation $r) {
                        $diff = (float) $r->closing_balance - (float) $r->book_closing_balance;

                        return abs($diff) <= 0.001 ? 'success' : 'danger';
                    })
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('matched_total')
                    ->label(__('accounting.bank_reconciliation.matched_total'))
                    ->getStateUsing(function (BankReconciliation $r) {
                        $total = $r->statementLines()->count();
                        $matched = $r->statementLines()
                            ->whereNotNull('matched_journal_entry_line_id')
                            ->count();

                        return "{$matched}/{$total}";
                    })
                    ->alignCenter()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        BankReconciliation::STATUS_IN_PROGRESS => 'warning',
                        BankReconciliation::STATUS_COMPLETED => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        BankReconciliation::STATUS_IN_PROGRESS => __('accounting.bank_reconciliation.status_in_progress'),
                        BankReconciliation::STATUS_COMPLETED => __('accounting.bank_reconciliation.status_completed'),
                        default => str_replace('_', ' ', ucwords($state, '_')),
                    }),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime('Y-m-d h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    BankReconciliation::STATUS_IN_PROGRESS => __('accounting.bank_reconciliation.status_in_progress'),
                    BankReconciliation::STATUS_COMPLETED => __('accounting.bank_reconciliation.status_completed'),
                ]),
                Tables\Filters\SelectFilter::make('account_id')
                    ->label(__('accounting.bank_reconciliation.account'))
                    ->options(fn () => self::bankAccountOptions())
                    ->searchable(),
                Tables\Filters\Filter::make('period_start_range')
                    ->label(__('accounting.bank_reconciliation.filter_period_start'))
                    ->form([
                        Forms\Components\DatePicker::make('from')->label(__('accounting.bank_reconciliation.filter_from')),
                        Forms\Components\DatePicker::make('to')->label(__('accounting.bank_reconciliation.filter_to')),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('period_start', '>=', $d))
                        ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('period_start', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (BankReconciliation $r) => $r->status === BankReconciliation::STATUS_IN_PROGRESS),

                Tables\Actions\Action::make('recompute')
                    ->label(__('accounting.bank_reconciliation.recompute'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (BankReconciliation $r) => $r->status === BankReconciliation::STATUS_IN_PROGRESS)
                    ->action(function (BankReconciliation $r) {
                        try {
                            app(BankReconciliationService::class)->recomputeBalances($r);
                            Notification::make()
                                ->title(__('accounting.bank_reconciliation.book_balances_refreshed'))
                                ->body(__('accounting.bank_reconciliation.closing_amount_body', [
                                    'amount' => number_format((float) $r->book_closing_balance, 3),
                                ]))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('accounting.bank_reconciliation.failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('autoMatch')
                    ->label(__('accounting.bank_reconciliation.auto_match'))
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting.bank_reconciliation.auto_match_modal_description'))
                    ->visible(fn (BankReconciliation $r) => $r->status === BankReconciliation::STATUS_IN_PROGRESS)
                    ->action(function (BankReconciliation $r) {
                        try {
                            $count = app(BankReconciliationService::class)->autoMatch($r);
                            Notification::make()
                                ->title(__('accounting.bank_reconciliation.auto_matched_title', ['count' => $count]))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('accounting.bank_reconciliation.failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('complete')
                    ->label(__('accounting.bank_reconciliation.mark_complete'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting.bank_reconciliation.mark_complete_modal_description'))
                    ->visible(fn (BankReconciliation $r) => $r->status === BankReconciliation::STATUS_IN_PROGRESS
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                    ->action(function (BankReconciliation $r) {
                        $r->forceFill([
                            'status' => BankReconciliation::STATUS_COMPLETED,
                            'completed_at' => now(),
                            'completed_by_user_id' => auth()->id(),
                        ])->save();

                        Notification::make()->title(__('accounting.bank_reconciliation.reconciliation_completed'))->success()->send();
                    }),

                Tables\Actions\Action::make('reopen')
                    ->label(__('accounting.bank_reconciliation.reopen'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting.bank_reconciliation.reopen_modal_description'))
                    ->visible(fn (BankReconciliation $r) => $r->status === BankReconciliation::STATUS_COMPLETED
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                    ->action(function (BankReconciliation $r) {
                        $r->forceFill([
                            'status' => BankReconciliation::STATUS_IN_PROGRESS,
                            'completed_at' => null,
                            'completed_by_user_id' => null,
                        ])->save();

                        Notification::make()->title(__('accounting.bank_reconciliation.reconciliation_reopened'))->success()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StatementLinesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['account']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankReconciliations::route('/'),
            'create' => Pages\CreateBankReconciliation::route('/create'),
            'edit' => Pages\EditBankReconciliation::route('/{record}/edit'),
        ];
    }

    /**
     * Eligible bank/cash accounts for reconciliation: type=asset with code
     * starting "1110" (cash), "1120" (bank) or "1130" (card clearing).
     * Returned as id => "code — name".
     */
    protected static function bankAccountOptions(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where(function (Builder $q) {
                $q->where('code', 'like', '1110%')
                    ->orWhere('code', 'like', '1120%')
                    ->orWhere('code', 'like', '1130%');
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])
            ->all();
    }
}
