<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\AccountingPeriodResource\Pages;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\PeriodCloseService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountingPeriodResource extends Resource
{
    protected static ?string $model = AccountingPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'accounting/periods';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.accounting_period.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.accounting_period.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.accounting_period.label_plural');
    }

    public static function form(Form $form): Form
    {
        // View-only form (everything disabled). Periods are auto-created and immutable.
        return $form->schema([
            Forms\Components\Section::make(__('accounting.accounting_period.section_period'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('accounting.accounting_period.code'))
                        ->disabled(),

                    Forms\Components\Select::make('status')
                        ->label(__('accounting.accounting_period.status'))
                        ->options([
                            AccountingPeriod::STATUS_OPEN => __('common.period_status.open'),
                            AccountingPeriod::STATUS_CLOSED => __('common.period_status.closed'),
                        ])
                        ->disabled(),

                    Forms\Components\DatePicker::make('start_date')
                        ->label(__('accounting.accounting_period.start_date'))
                        ->native(false)
                        ->disabled(),

                    Forms\Components\DatePicker::make('end_date')
                        ->label(__('accounting.accounting_period.end_date'))
                        ->native(false)
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('closed_at')
                        ->label(__('accounting.accounting_period.closed_at'))
                        ->native(false)
                        ->disabled(),

                    Forms\Components\Textarea::make('notes')
                        ->label(__('accounting.accounting_period.notes'))
                        ->rows(3)
                        ->columnSpanFull()
                        ->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('accounting.accounting_period.code'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('accounting.accounting_period.start_date'))
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('accounting.accounting_period.end_date'))
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('accounting.accounting_period.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        AccountingPeriod::STATUS_OPEN => 'success',
                        AccountingPeriod::STATUS_CLOSED => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('common.period_status.'.$state)),

                Tables\Columns\TextColumn::make('closing_je')
                    ->label(__('accounting.accounting_period.closing_je'))
                    ->getStateUsing(function (AccountingPeriod $r) {
                        if (! $r->isClosed()) {
                            return null;
                        }
                        $je = JournalEntry::query()
                            ->where('source_type', AccountingPeriod::class)
                            ->where('source_id', $r->id)
                            ->where('status', JournalEntry::STATUS_POSTED)
                            ->latest('id')
                            ->first();

                        return $je?->code;
                    })
                    ->placeholder(__('accounting.accounting_period.placeholder_dash'))
                    ->fontFamily('mono')
                    ->copyable(),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label(__('accounting.accounting_period.closed_at'))
                    ->dateTime('Y-m-d h:i A')
                    ->placeholder(__('accounting.accounting_period.placeholder_dash'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label(__('accounting.accounting_period.closed_by'))
                    ->placeholder(__('accounting.accounting_period.placeholder_dash'))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('accounting.accounting_period.status'))
                    ->options([
                        AccountingPeriod::STATUS_OPEN => __('common.period_status.open'),
                        AccountingPeriod::STATUS_CLOSED => __('common.period_status.closed'),
                    ]),
                Tables\Filters\SelectFilter::make('year')
                    ->label(__('accounting.accounting_period.year'))
                    ->options(function (): array {
                        $years = AccountingPeriod::query()
                            ->selectRaw('YEAR(start_date) as y')
                            ->distinct()
                            ->orderByDesc('y')
                            ->pluck('y')
                            ->all();

                        return array_combine($years, $years);
                    })
                    ->query(fn (Builder $q, array $data) => $q->when(
                        $data['value'] ?? null,
                        fn ($qq, $y) => $qq->whereYear('start_date', $y)
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('close')
                    ->label(__('accounting.accounting_period.close_period'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting.accounting_period.close_modal_description'))
                    ->visible(fn (AccountingPeriod $r) => $r->status === AccountingPeriod::STATUS_OPEN
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                    ->action(function (AccountingPeriod $r) {
                        try {
                            $je = app(PeriodCloseService::class)->close($r, (int) (auth()->id() ?? 0));
                            Notification::make()
                                ->title(__('accounting.accounting_period.period_closed_title', ['code' => $r->code]))
                                ->body(__('accounting.accounting_period.closing_je_body', ['code' => $je->code]))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('accounting.accounting_period.cannot_close_period'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('reopen')
                    ->label(__('accounting.accounting_period.reopen_period'))
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting.accounting_period.reopen_modal_description'))
                    ->visible(fn (AccountingPeriod $r) => $r->status === AccountingPeriod::STATUS_CLOSED
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                    ->action(function (AccountingPeriod $r) {
                        try {
                            app(PeriodCloseService::class)->reopen($r, (int) (auth()->id() ?? 0));
                            Notification::make()
                                ->title(__('accounting.accounting_period.period_reopened_title', ['code' => $r->code]))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('accounting.accounting_period.cannot_reopen_period'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_closing_je')
                    ->label(__('accounting.accounting_period.view_closing_je'))
                    ->icon('heroicon-o-book-open')
                    ->color('gray')
                    ->visible(fn (AccountingPeriod $r) => $r->status === AccountingPeriod::STATUS_CLOSED)
                    ->url(function (AccountingPeriod $r): ?string {
                        $je = JournalEntry::query()
                            ->where('source_type', AccountingPeriod::class)
                            ->where('source_id', $r->id)
                            ->where('status', JournalEntry::STATUS_POSTED)
                            ->latest('id')
                            ->first();

                        return $je
                            ? JournalEntryResource::getUrl('view', ['record' => $je->id])
                            : null;
                    })
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('closedBy');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingPeriods::route('/'),
            'view' => Pages\ViewAccountingPeriod::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
