<?php

namespace App\Filament\Resources\Accounting;

use App\Filament\Resources\Accounting\JournalEntryResource\Pages;
use App\Filament\Resources\Accounting\JournalEntryResource\RelationManagers\LinesRelationManager;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = null;

    protected static ?string $slug = 'accounting/journal-entries';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.journal_entry.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.journal_entry.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.journal_entry.label_plural');
    }

    public static function form(Form $form): Form
    {
        // Lock the entire form on edit unless the record is still a draft.
        // Posted/reversed entries are immutable.
        $lockedOnEdit = fn (?JournalEntry $record): bool => $record !== null
            && $record->status !== JournalEntry::STATUS_DRAFT;

        return $form->schema([
            Forms\Components\Section::make(__('accounting.journal_entry.section_entry'))
                ->columns(3)
                ->schema([
                    Forms\Components\DatePicker::make('entry_date')
                        ->label(__('accounting.journal_entry.date'))
                        ->required()
                        ->native(false)
                        ->default(now())
                        ->disabled($lockedOnEdit),

                    Forms\Components\Select::make('branch_id')
                        ->label(__('accounting.journal_entry.branch'))
                        ->relationship('branch', 'name')
                        ->searchable()
                        ->preload()
                        ->disabled($lockedOnEdit),

                    Forms\Components\TextInput::make('currency')
                        ->label(__('accounting.journal_entry.currency'))
                        ->default('KWD')
                        ->maxLength(3)
                        ->required()
                        ->disabled($lockedOnEdit),

                    Forms\Components\Textarea::make('narration')
                        ->label(__('accounting.journal_entry.narration'))
                        ->required()
                        ->rows(2)
                        ->columnSpanFull()
                        ->maxLength(500)
                        ->disabled($lockedOnEdit),
                ]),

            Forms\Components\Section::make(__('accounting.journal_entry.section_lines'))
                ->columns(1)
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship('lines')
                        ->minItems(2)
                        ->defaultItems(2)
                        ->reorderable()
                        ->addActionLabel(__('accounting.journal_entry.add_line'))
                        ->columns(5)
                        ->live()
                        ->disabled($lockedOnEdit)
                        ->schema([
                            Forms\Components\Select::make('account_id')
                                ->label(__('accounting.journal_entry.account'))
                                ->relationship('account', 'name', fn (Builder $query) => $query->where('is_active', true))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->getOptionLabelFromRecordUsing(fn (Account $r) => $r->code.' — '.$r->name),

                            Forms\Components\TextInput::make('debit')
                                ->label(__('accounting.journal_entry.debit'))
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0)
                                ->default(0),

                            Forms\Components\TextInput::make('credit')
                                ->label(__('accounting.journal_entry.credit'))
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0)
                                ->default(0),

                            Forms\Components\TextInput::make('description')
                                ->label(__('accounting.journal_entry.description'))
                                ->maxLength(191)
                                ->columnSpan(2),
                        ]),

                    Forms\Components\Placeholder::make('balance_check')
                        ->label(__('accounting.journal_entry.balance'))
                        ->content(function (Forms\Get $get): string {
                            $lines = $get('lines') ?? [];
                            $debit = 0.0;
                            $credit = 0.0;
                            foreach ($lines as $line) {
                                $debit += (float) ($line['debit'] ?? 0);
                                $credit += (float) ($line['credit'] ?? 0);
                            }
                            $diff = abs($debit - $credit);
                            $debitFmt = number_format($debit, 3);
                            $creditFmt = number_format($credit, 3);
                            if ($diff <= 0.001) {
                                return __('accounting.journal_entry.balance_balanced', [
                                    'debit' => $debitFmt,
                                    'credit' => $creditFmt,
                                ]);
                            }

                            return __('accounting.journal_entry.balance_off', [
                                'debit' => $debitFmt,
                                'credit' => $creditFmt,
                                'diff' => number_format($diff, 3),
                            ]);
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('accounting.journal_entry.code'))
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable(),

                Tables\Columns\TextColumn::make('entry_date')
                    ->label(__('accounting.journal_entry.date'))
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('narration')
                    ->label(__('accounting.journal_entry.narration'))
                    ->wrap()
                    ->limit(80)
                    ->searchable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label(__('accounting.journal_entry.source'))
                    ->formatStateUsing(fn (?string $state) => $state
                        ? class_basename($state)
                        : '—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('totalDebit')
                    ->label(__('accounting.journal_entry.debit'))
                    ->getStateUsing(fn (JournalEntry $r) => number_format($r->totalDebit(), 3))
                    ->alignEnd()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('totalCredit')
                    ->label(__('accounting.journal_entry.credit'))
                    ->getStateUsing(fn (JournalEntry $r) => number_format($r->totalCredit(), 3))
                    ->alignEnd()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('accounting.journal_entry.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        JournalEntry::STATUS_POSTED => 'success',
                        JournalEntry::STATUS_DRAFT => 'warning',
                        JournalEntry::STATUS_REVERSED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('common.je_status.'.$state)),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('accounting.journal_entry.branch'))
                    ->placeholder(__('accounting.journal_entry.placeholder_dash'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('posted_at')
                    ->dateTime('Y-m-d h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('postedBy.name')
                    ->label(__('accounting.journal_entry.posted_by'))
                    ->placeholder(__('accounting.journal_entry.placeholder_system'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('accounting.journal_entry.status'))
                    ->options([
                        JournalEntry::STATUS_DRAFT => __('common.je_status.draft'),
                        JournalEntry::STATUS_POSTED => __('common.je_status.posted'),
                        JournalEntry::STATUS_REVERSED => __('common.je_status.reversed'),
                    ]),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label(__('accounting.journal_entry.filter_from')),
                        Forms\Components\DatePicker::make('to')->label(__('accounting.journal_entry.filter_to')),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($qq, $d) => $qq->whereDate('entry_date', '>=', $d))
                        ->when($data['to'] ?? null, fn ($qq, $d) => $qq->whereDate('entry_date', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (JournalEntry $r) => $r->status === JournalEntry::STATUS_DRAFT
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),
                Tables\Actions\Action::make('reverse')
                    ->label(__('accounting.journal_entry.reverse'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting.journal_entry.reverse_modal_description'))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('accounting.journal_entry.reverse_reason'))
                            ->rows(2)
                            ->required(),
                    ])
                    ->visible(fn (JournalEntry $r) => $r->status === JournalEntry::STATUS_POSTED
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                    ->action(function (JournalEntry $r, array $data) {
                        try {
                            $reversal = $r->reverse((int) (auth()->id() ?? 0), $data['reason']);
                            Notification::make()
                                ->title(__('accounting.journal_entry.entry_reversed'))
                                ->body(__('accounting.journal_entry.reversal_body', ['code' => $reversal->code]))
                                ->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('accounting.journal_entry.failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('post')
                    ->label(__('accounting.journal_entry.post_draft'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(__('accounting.journal_entry.post_modal_description'))
                    ->visible(fn (JournalEntry $r) => $r->status === JournalEntry::STATUS_DRAFT
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false))
                    ->action(function (JournalEntry $r) {
                        try {
                            $r->post((int) (auth()->id() ?? 0));
                            Notification::make()->title(__('accounting.journal_entry.entry_posted'))->body($r->code)->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('accounting.journal_entry.cannot_post'))->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (JournalEntry $r) => $r->status === JournalEntry::STATUS_DRAFT
                        && (auth()->user()?->hasRole(['admin', 'super_admin']) ?? false)),
            ])
            ->emptyStateHeading(__('resources.journal_entry.empty_heading'))
            ->emptyStateDescription(__('resources.journal_entry.empty_description'))
            ->emptyStateIcon('heroicon-o-book-open');
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['lines', 'branch', 'postedBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'create' => Pages\CreateJournalEntry::route('/create'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
            'edit' => Pages\EditJournalEntry::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(['admin', 'super_admin']) ?? false;
    }
}
