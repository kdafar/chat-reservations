<?php

namespace App\Filament\Pages\Accounting;

use App\Filament\Concerns\HasHelpAction;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * The Trial Balance is the foundational accounting report: for a given
 * date range, sum debits and credits per account. Total debits MUST equal
 * total credits across the entire CoA — that's the integrity check.
 */
class TrialBalance extends Page implements HasForms
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
            ['heading' => __('help.pages.trial_balance.what.heading'), 'body' => __('help.pages.trial_balance.what.body')],
            ['heading' => __('help.pages.trial_balance.how.heading'), 'items' => (array) trans('help.pages.trial_balance.how.items')],
            ['heading' => __('help.pages.trial_balance.faq.heading'), 'items' => (array) trans('help.pages.trial_balance.faq.items')],
        ];
    }

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.accounting.trial-balance';

    public ?array $filters = [];

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.trial_balance.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.trial_balance.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('view_accounting_trial_balance');
    }

    public function mount(): void
    {
        $this->form->fill([
            'filters' => [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ])
            ->columns(2);
    }

    public function getRows(): array
    {
        $from = $this->filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['to'] ?? now()->toDateString();

        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->select(
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                DB::raw('SUM(l.debit) as debit_sum'),
                DB::raw('SUM(l.credit) as credit_sum'),
            )
            ->get();

        return $rows->map(function ($r) {
            $debit = (float) $r->debit_sum;
            $credit = (float) $r->credit_sum;
            $isDebitNormal = in_array($r->type, Account::DEBIT_NORMAL_TYPES, true);
            $net = $isDebitNormal ? $debit - $credit : $credit - $debit;

            return [
                'code' => $r->code,
                'name' => $r->name,
                'type' => $r->type,
                'is_debit_normal' => $isDebitNormal,
                'debit' => $debit,
                'credit' => $credit,
                'net' => $net,
                // For display: show the natural-balance amount in its native column
                'display_debit' => $isDebitNormal && $net > 0 ? $net : ($isDebitNormal ? 0 : 0),
                'display_credit' => ! $isDebitNormal && $net > 0 ? $net : 0,
                'raw_debit' => $debit,
                'raw_credit' => $credit,
            ];
        })->all();
    }

    public function getViewData(): array
    {
        $rows = $this->getRows();
        $totalDebit = array_sum(array_column($rows, 'raw_debit'));
        $totalCredit = array_sum(array_column($rows, 'raw_credit'));

        return [
            'rows' => $rows,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) < 0.01,
            'from' => Carbon::parse($this->filters['from'] ?? now()->startOfMonth())->format('d M Y'),
            'to' => Carbon::parse($this->filters['to'] ?? now())->format('d M Y'),
        ];
    }
}
