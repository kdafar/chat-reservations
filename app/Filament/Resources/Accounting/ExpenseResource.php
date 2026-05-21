<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\ExpenseResource\Pages;
use App\Models\Accounting\Account;
use App\Models\Accounting\Expense;
use App\Models\Accounting\Vendor;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'accounting/expenses';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.expense.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.expense.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.expense.label_plural');
    }

    // -------------------------------------------------------------------------
    // Helpers — account option builders
    // -------------------------------------------------------------------------

    /** @return array<int, string> */
    protected static function expenseAccountOptions(): array
    {
        return Account::query()
            ->whereIn('type', [Account::TYPE_EXPENSE, Account::TYPE_COGS])
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])
            ->toArray();
    }

    /** Cash / bank asset accounts (1010, 1020, 1021, 1022 and branch sub-accounts). */
    protected static function paymentAccountOptions(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('code', ['1010', '1020', '1021', '1022'])
                    ->orWhere('code', 'like', '1010-%')
                    ->orWhere('code', 'like', '1020-%');
            })
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"])
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Expense')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Code')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Auto-generated on save'),

                    Forms\Components\DatePicker::make('expense_date')
                        ->label('Date')
                        ->required()
                        ->default(now()->toDateString()),

                    Forms\Components\Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(fn () => Vendor::active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required()->maxLength(191),
                            Forms\Components\TextInput::make('phone')->tel()->maxLength(64),
                            Forms\Components\TextInput::make('email')->email()->maxLength(191),
                            Forms\Components\Toggle::make('is_active')->default(true),
                        ])
                        ->createOptionUsing(fn (array $data) => Vendor::create($data)->id)
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state && $vendor = Vendor::find($state)) {
                                if ($vendor->default_account_id) {
                                    $set('account_id', $vendor->default_account_id);
                                }
                            }
                        })
                        ->live(),

                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('account_id')
                        ->label('Expense Account')
                        ->options(fn () => self::expenseAccountOptions())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('The expense category being debited (e.g. 6030 Rent).'),

                    Forms\Components\TextInput::make('amount')
                        ->label('Amount (KWD)')
                        ->numeric()
                        ->required()
                        ->step(0.001)
                        ->minValue(0.001)
                        ->prefix('KWD'),

                    Forms\Components\Textarea::make('description')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Payment')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('payment_account_id')
                        ->label('Paid From')
                        ->options(fn () => self::paymentAccountOptions())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Leave empty if billed to Accounts Payable.'),

                    Forms\Components\TextInput::make('reference_no')
                        ->label('Reference / Invoice No.')
                        ->maxLength(191),

                    Forms\Components\FileUpload::make('receipt_path')
                        ->label('Receipt')
                        ->directory('accounting/expenses')
                        ->disk('public')
                        ->image()
                        ->imagePreviewHeight('120')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->description(fn (Expense $r) => $r->account?->code)
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount (KWD)')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->weight('semibold')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Expense::STATUS_DRAFT => 'warning',
                        Expense::STATUS_POSTED => 'success',
                        Expense::STATUS_VOID => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('paymentAccount.name')
                    ->label('Paid From')
                    ->placeholder('On Account')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Reference')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Expense::STATUS_DRAFT => 'Draft',
                        Expense::STATUS_POSTED => 'Posted',
                        Expense::STATUS_VOID => 'Void',
                    ]),

                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('account_id')
                    ->label('Account')
                    ->options(fn () => self::expenseAccountOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('expense_date', '>=', $d))
                        ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('expense_date', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (Expense $r) => $r->status === Expense::STATUS_DRAFT),

                    Tables\Actions\Action::make('post')
                        ->label('Post')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Posts this expense to the General Ledger. This cannot be edited afterwards.')
                        ->visible(fn (Expense $r) => $r->status === Expense::STATUS_DRAFT)
                        ->action(function (Expense $r) {
                            try {
                                $r->post((int) (auth()->id() ?? 0));
                                if ($r->refresh()->status === Expense::STATUS_POSTED) {
                                    Notification::make()
                                        ->title('Expense posted')
                                        ->body('JE: '.($r->journalEntry?->code ?? '#'.$r->journal_entry_id))
                                        ->success()->send();
                                } else {
                                    Notification::make()
                                        ->title('Posting failed')
                                        ->body('Check that all required accounts are configured.')
                                        ->danger()->send();
                                }
                            } catch (\Throwable $e) {
                                Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('void')
                        ->label('Void')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Reverses the journal entry and marks this expense as void. The audit trail is preserved.')
                        ->visible(fn (Expense $r) => $r->status === Expense::STATUS_POSTED
                            && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                        ->action(function (Expense $r) {
                            try {
                                $r->void((int) (auth()->id() ?? 0));
                                Notification::make()->title('Expense voided')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading(__('resources.expense.empty_heading'))
            ->emptyStateDescription(__('resources.expense.empty_description'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendor', 'account', 'paymentAccount', 'branch', 'journalEntry']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
