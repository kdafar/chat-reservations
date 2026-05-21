<?php

namespace App\Filament\Pages\Accounting;

use App\Filament\Concerns\HasHelpAction;
use App\Filament\Resources\Accounting\JournalEntryResource;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Branch;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger drill-down: pick a single account + date range and see every
 * posted journal-entry-line that hit that account in chronological order, with
 * a running balance computed in the account's natural direction.
 */
class GeneralLedger extends Page implements HasForms
{
    use HasHelpAction;
    use InteractsWithForms;

    protected function getHeaderActions(): array
    {
        return $this->withHelp([]);
    }

    protected function helpContent(): array
    {
        return [
            ['heading' => __('help.pages.general_ledger.what.heading'), 'body' => __('help.pages.general_ledger.what.body')],
            ['heading' => __('help.pages.general_ledger.how.heading'), 'items' => (array) trans('help.pages.general_ledger.how.items')],
            ['heading' => __('help.pages.general_ledger.faq.heading'), 'items' => (array) trans('help.pages.general_ledger.faq.items')],
        ];
    }

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'accounting/general-ledger';

    protected static string $view = 'filament.pages.accounting.general-ledger';

    public ?array $filters = [];

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.general_ledger.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.general_ledger.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('view_accounting_general_ledger');
    }

    public function mount(): void
    {
        $this->form->fill([
            'filters' => [
                'account_id' => null,
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
                'branch_id' => null,
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('filters.account_id')
                    ->label('Account')
                    ->placeholder('Select an account…')
                    ->options(function () {
                        return Account::query()
                            ->where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => "{$a->code} — {$a->name}"])
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->columnSpan(2),
                DatePicker::make('filters.from')
                    ->label('From')
                    ->native(false)
                    ->default(now()->startOfMonth())
                    ->required()
                    ->live(),
                DatePicker::make('filters.to')
                    ->label('To')
                    ->native(false)
                    ->default(now())
                    ->required()
                    ->live(),
                Select::make('filters.branch_id')
                    ->label('Branch (optional)')
                    ->placeholder('All branches')
                    ->options(function () {
                        // Branch.name is translatable JSON; resolve through the model accessor.
                        return Branch::query()->get()
                            ->mapWithKeys(fn ($b) => [$b->id => (string) $b->name])
                            ->sort()
                            ->all();
                    })
                    ->searchable()
                    ->live()
                    ->columnSpan(2),
            ])
            ->columns(2);
    }

    /**
     * Pull all posted lines for the chosen account within range, join the entry
     * and source bits we need, then walk them in chronological order to compute
     * a running balance in the account's natural direction.
     */
    public function getViewData(): array
    {
        $accountId = $this->filters['account_id'] ?? null;
        $from = $this->filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['to'] ?? now()->toDateString();
        $branchId = $this->filters['branch_id'] ?? null;

        // No account chosen → render the picker prompt state.
        if (! $accountId) {
            return [
                'account' => null,
                'rows' => [],
                'opening_balance' => 0.0,
                'closing_balance' => 0.0,
                'period_activity' => 0.0,
                'from' => Carbon::parse($from)->format('d M Y'),
                'to' => Carbon::parse($to)->format('d M Y'),
                'branch' => null,
            ];
        }

        /** @var Account|null $account */
        $account = Account::find($accountId);
        if (! $account) {
            return [
                'account' => null,
                'rows' => [],
                'opening_balance' => 0.0,
                'closing_balance' => 0.0,
                'period_activity' => 0.0,
                'from' => Carbon::parse($from)->format('d M Y'),
                'to' => Carbon::parse($to)->format('d M Y'),
                'branch' => null,
            ];
        }

        $openingDate = Carbon::parse($from)->subDay()->toDateString();
        $openingBalance = $account->balanceAt($openingDate);

        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->leftJoin('doctors as d', 'd.id', '=', 'l.doctor_id')
            ->leftJoin('patients as p', 'p.id', '=', 'l.patient_id')
            ->where('l.account_id', $account->id)
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            // Chronological ordering: by entry_date, then JE id (post order),
            // then line id — gives a stable, repeatable running balance.
            ->orderBy('e.entry_date')
            ->orderBy('e.id')
            ->orderBy('l.id')
            ->select(
                'l.id as line_id',
                'l.debit',
                'l.credit',
                'l.description as line_description',
                'l.branch_id',
                'l.doctor_id',
                'l.patient_id',
                'e.id as je_id',
                'e.code as je_code',
                'e.entry_date',
                'e.narration',
                'e.source_type',
                'e.source_id',
                'd.name as doctor_name',
                'p.name as patient_name',
            );

        if ($branchId) {
            $query->where('l.branch_id', $branchId);
        }

        $rawRows = $query->get();

        // Branch.name is translatable (JSON column) — resolve via the Eloquent
        // accessor so it picks the current locale string instead of raw JSON.
        $branchIds = $rawRows->pluck('branch_id')->filter()->unique()->all();
        $branchNames = $branchIds
            ? Branch::query()->whereIn('id', $branchIds)->get()->mapWithKeys(fn ($b) => [$b->id => (string) $b->name])->all()
            : [];

        $isDebitNormal = $account->isDebitNormal();
        $balance = $openingBalance;
        $rows = [];

        foreach ($rawRows as $r) {
            $debit = (float) $r->debit;
            $credit = (float) $r->credit;
            $delta = $isDebitNormal ? ($debit - $credit) : ($credit - $debit);
            $balance += $delta;

            $sourceLabel = null;
            if ($r->source_type && $r->source_id) {
                $sourceLabel = class_basename($r->source_type).' #'.$r->source_id;
            }

            try {
                $jeUrl = JournalEntryResource::getUrl('view', ['record' => $r->je_id]);
            } catch (\Throwable $e) {
                $jeUrl = null;
            }

            $rows[] = [
                'line_id' => $r->line_id,
                'je_id' => $r->je_id,
                'je_code' => $r->je_code,
                'je_url' => $jeUrl,
                'entry_date' => $r->entry_date,
                'description' => $r->line_description ?: $r->narration,
                'source_label' => $sourceLabel,
                'branch_name' => $r->branch_id ? ($branchNames[$r->branch_id] ?? null) : null,
                'doctor_name' => $r->doctor_name,
                'patient_name' => $r->patient_name,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $balance,
            ];
        }

        $closingBalance = $balance; // Equal to $account->balanceAt($to) when range covers all activity through $to.
        $periodActivity = $closingBalance - $openingBalance;

        $branch = $branchId ? Branch::find($branchId) : null;

        return [
            'account' => $account,
            'rows' => $rows,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'period_activity' => $periodActivity,
            'from' => Carbon::parse($from)->format('d M Y'),
            'to' => Carbon::parse($to)->format('d M Y'),
            'branch' => $branch,
        ];
    }
}
