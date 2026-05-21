<?php

namespace App\Filament\Resources\Accounting\BankReconciliationResource\RelationManagers;

use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankStatementLine;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\BankReconciliationService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class StatementLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'statementLines';

    protected static ?string $title = 'Statement Lines';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('statement_date')
                ->label('Date')
                ->required()
                ->native(false),

            Forms\Components\TextInput::make('description')
                ->label('Description')
                ->maxLength(500)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('reference')
                ->label('Reference')
                ->maxLength(191),

            Forms\Components\TextInput::make('debit')
                ->label('Debit (deposit IN)')
                ->numeric()
                ->step(0.001)
                ->minValue(0)
                ->default(0)
                ->prefix('KWD'),

            Forms\Components\TextInput::make('credit')
                ->label('Credit (withdrawal OUT)')
                ->numeric()
                ->step(0.001)
                ->minValue(0)
                ->default(0)
                ->prefix('KWD'),

            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('statement_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('statement_date')
                    ->label('Date')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('reference')
                    ->fontFamily('mono')
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('debit')
                    ->label('Debit (in)')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => (float) $state > 0
                        ? number_format((float) $state, 3).' KWD'
                        : '—'),

                Tables\Columns\TextColumn::make('credit')
                    ->label('Credit (out)')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->formatStateUsing(fn ($state) => (float) $state > 0
                        ? number_format((float) $state, 3).' KWD'
                        : '—'),

                Tables\Columns\IconColumn::make('matched')
                    ->label('Matched')
                    ->getStateUsing(fn (BankStatementLine $r) => $r->isMatched())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('matchedLine.entry.code')
                    ->label('JE Code')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('matched')
                    ->label('Matched')
                    ->placeholder('All')
                    ->trueLabel('Matched only')
                    ->falseLabel('Unmatched only')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('matched_journal_entry_line_id'),
                        false: fn ($q) => $q->whereNull('matched_journal_entry_line_id'),
                        blank: fn ($q) => $q,
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->reconciliationIsEditable()),

                Tables\Actions\Action::make('importCsv')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->visible(fn () => $this->reconciliationIsEditable())
                    ->form([
                        Forms\Components\FileUpload::make('csv')
                            ->label('CSV file')
                            ->required()
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->disk('local')
                            ->directory('tmp/bank-imports')
                            ->helperText('Columns: date, description, debit, credit, reference (header row required).'),
                    ])
                    ->action(function (array $data) {
                        $rec = $this->getOwnerRecord();
                        if (! $rec instanceof BankReconciliation) {
                            return;
                        }

                        $path = $data['csv'] ?? null;
                        if (! $path) {
                            Notification::make()->title('No file uploaded')->danger()->send();

                            return;
                        }

                        // Resolve to an absolute filesystem path via the disk used above.
                        $absolutePath = Storage::disk('local')->path($path);

                        try {
                            $count = app(BankReconciliationService::class)->importCsv($rec, $absolutePath);
                            Notification::make()
                                ->title("Imported {$count} row(s)")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
                        } finally {
                            // Tidy up the upload file regardless of outcome.
                            try {
                                Storage::disk('local')->delete($path);
                            } catch (\Throwable $e) {
                                // ignore
                            }
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (BankStatementLine $r) => ! $r->isMatched() && $this->reconciliationIsEditable()),

                Tables\Actions\Action::make('match')
                    ->label('Match to JE')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (BankStatementLine $r) => ! $r->isMatched() && $this->reconciliationIsEditable())
                    ->form(fn (BankStatementLine $r) => [
                        Forms\Components\Select::make('journal_entry_line_id')
                            ->label('Journal Entry Line')
                            ->required()
                            ->searchable()
                            ->options(fn () => $this->matchableJournalLines($r))
                            ->helperText('Unmatched JE lines on the same account within this period.'),
                    ])
                    ->action(function (BankStatementLine $r, array $data) {
                        try {
                            $r->match((int) $data['journal_entry_line_id'], auth()->id());
                            Notification::make()->title('Matched')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Match failed')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('unmatch')
                    ->label('Unmatch')
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (BankStatementLine $r) => $r->isMatched() && $this->reconciliationIsEditable())
                    ->action(function (BankStatementLine $r) {
                        $r->unmatch();
                        Notification::make()->title('Unmatched')->success()->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (BankStatementLine $r) => ! $r->isMatched() && $this->reconciliationIsEditable()),
            ]);
    }

    protected function reconciliationIsEditable(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof BankReconciliation
            && $owner->status === BankReconciliation::STATUS_IN_PROGRESS;
    }

    /**
     * Build the option list for the "Match to JE" picker: unmatched journal
     * entry lines on the same account, in the same period.
     *
     * Returns id => "date • code • Dr X / Cr Y • description".
     */
    protected function matchableJournalLines(BankStatementLine $line): array
    {
        $rec = $this->getOwnerRecord();
        if (! $rec instanceof BankReconciliation) {
            return [];
        }

        // Get IDs already matched in THIS reconciliation, plus the current
        // line's existing match if any (should be null, but be defensive).
        $alreadyMatched = $rec->statementLines()
            ->whereNotNull('matched_journal_entry_line_id')
            ->where('id', '!=', $line->id)
            ->pluck('matched_journal_entry_line_id')
            ->all();

        $rows = JournalEntryLine::query()
            ->where('account_id', $rec->account_id)
            ->whereHas('entry', function ($q) use ($rec) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                    ->whereBetween('entry_date', [
                        $rec->period_start->toDateString(),
                        $rec->period_end->toDateString(),
                    ]);
            })
            ->when(! empty($alreadyMatched), fn ($q) => $q->whereNotIn('id', $alreadyMatched))
            ->with('entry')
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get();

        return $rows->mapWithKeys(function (JournalEntryLine $r) {
            $date = $r->entry?->entry_date?->format('Y-m-d') ?? '—';
            $code = $r->entry?->code ?? '(no code)';
            $dr = (float) $r->debit;
            $cr = (float) $r->credit;
            $amount = $dr > 0
                ? 'Dr '.number_format($dr, 3)
                : 'Cr '.number_format($cr, 3);
            $desc = $r->description ? ' • '.\Illuminate\Support\Str::limit($r->description, 40) : '';

            return [$r->id => "{$date} • {$code} • {$amount}{$desc}"];
        })->all();
    }
}
