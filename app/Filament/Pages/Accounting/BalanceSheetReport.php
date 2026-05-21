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
 * Balance Sheet: snapshot of Assets vs Liabilities + Equity at a point in time.
 *
 * Assets = Liabilities + Equity must hold. Equity includes "current-period
 * Retained Earnings" — the running net income from fiscal-year-start through
 * the as-of date — because we don't auto-close revenue/expense to retained
 * earnings until a year-end close runs.
 */
class BalanceSheetReport extends Page implements HasForms
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
            ['heading' => __('help.pages.balance_sheet_report.what.heading'), 'body' => __('help.pages.balance_sheet_report.what.body')],
            ['heading' => __('help.pages.balance_sheet_report.how.heading'), 'items' => (array) trans('help.pages.balance_sheet_report.how.items')],
            ['heading' => __('help.pages.balance_sheet_report.faq.heading'), 'items' => (array) trans('help.pages.balance_sheet_report.faq.items')],
        ];
    }

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 70;

    protected static string $view = 'filament.pages.accounting.balance-sheet-report';

    public ?array $filters = [];

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.balance_sheet_report.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.balance_sheet_report.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('view_accounting_balance_sheet');
    }

    public function mount(): void
    {
        $this->form->fill([
            'filters' => [
                'as_of' => now()->toDateString(),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('filters.as_of')
                    ->label('As of date')
                    ->native(false)
                    ->default(now())
                    ->required()
                    ->live(),
            ])
            ->columns(2);
    }

    /**
     * Per-account balance at $asOf, signed in natural direction.
     * Returns [account_id => float] for accounts of the given types.
     */
    protected function balancesAt(array $types, string $asOf): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereDate('e.entry_date', '<=', $asOf)
            ->whereIn('a.type', $types)
            ->groupBy('a.id', 'a.type')
            ->select(
                'a.id',
                'a.type',
                DB::raw('SUM(l.debit) as debit_sum'),
                DB::raw('SUM(l.credit) as credit_sum'),
            )
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $debit = (float) $r->debit_sum;
            $credit = (float) $r->credit_sum;
            $isDebitNormal = in_array($r->type, Account::DEBIT_NORMAL_TYPES, true);
            $out[$r->id] = $isDebitNormal ? $debit - $credit : $credit - $debit;
        }

        return $out;
    }

    /**
     * Build hierarchical rows for a set of types, given pre-computed balances.
     */
    protected function buildSection(array $types, array $balances): array
    {
        $accounts = Account::query()
            ->whereIn('type', $types)
            ->orderBy('code')
            ->get();

        $byId = $accounts->keyBy('id');
        $rolled = [];
        foreach ($accounts as $a) {
            $rolled[$a->id] = (float) ($balances[$a->id] ?? 0.0);
        }
        foreach ($accounts as $a) {
            $own = (float) ($balances[$a->id] ?? 0.0);
            if ($own == 0.0) {
                continue;
            }
            $parentId = $a->parent_id;
            while ($parentId && isset($byId[$parentId])) {
                $rolled[$parentId] += $own;
                $parentId = $byId[$parentId]->parent_id;
            }
        }

        $rows = [];
        $emit = function ($node, $depth) use (&$emit, $accounts, $balances, $rolled, &$rows) {
            $own = (float) ($balances[$node->id] ?? 0.0);
            $rollup = (float) ($rolled[$node->id] ?? 0.0);
            if (abs($own) < 0.0005 && abs($rollup) < 0.0005) {
                return;
            }
            $rows[] = [
                'id' => $node->id,
                'code' => $node->code,
                'name' => $node->name,
                'type' => $node->type,
                'depth' => $depth,
                'is_parent' => $accounts->where('parent_id', $node->id)->isNotEmpty(),
                'own' => $own,
                'rollup' => $rollup,
            ];
            foreach ($accounts->where('parent_id', $node->id)->sortBy('code') as $child) {
                $emit($child, $depth + 1);
            }
        };
        foreach ($accounts as $a) {
            if (! $a->parent_id || ! isset($byId[$a->parent_id])) {
                $emit($a, 0);
            }
        }

        return $rows;
    }

    public function getViewData(): array
    {
        $asOf = $this->filters['as_of'] ?? now()->toDateString();
        $asOfCarbon = Carbon::parse($asOf);

        // Assets side
        $assetBalances = $this->balancesAt([Account::TYPE_ASSET], $asOf);
        $contraAssetBalances = $this->balancesAt([Account::TYPE_CONTRA_ASSET], $asOf);

        $assetsRows = $this->buildSection([Account::TYPE_ASSET], $assetBalances);
        $contraAssetsRows = $this->buildSection([Account::TYPE_CONTRA_ASSET], $contraAssetBalances);

        $totalAssetsGross = array_sum($assetBalances);
        $totalContraAssets = array_sum($contraAssetBalances);
        $totalAssets = $totalAssetsGross - $totalContraAssets;

        // Liabilities + Equity side
        $liabilityBalances = $this->balancesAt([Account::TYPE_LIABILITY], $asOf);
        $contraLiabilityBalances = $this->balancesAt([Account::TYPE_CONTRA_LIABILITY], $asOf);
        $equityBalances = $this->balancesAt([Account::TYPE_EQUITY], $asOf);

        $liabilitiesRows = $this->buildSection([Account::TYPE_LIABILITY], $liabilityBalances);
        $contraLiabilitiesRows = $this->buildSection([Account::TYPE_CONTRA_LIABILITY], $contraLiabilityBalances);
        $equityRows = $this->buildSection([Account::TYPE_EQUITY], $equityBalances);

        $totalLiabilitiesGross = array_sum($liabilityBalances);
        $totalContraLiabilities = array_sum($contraLiabilityBalances);
        $totalLiabilities = $totalLiabilitiesGross - $totalContraLiabilities;

        $totalEquityBooked = array_sum($equityBalances);

        // Current-period Retained Earnings: fiscal year is calendar year (Jan 1 → as_of).
        $fiscalStart = $asOfCarbon->copy()->startOfYear()->toDateString();
        $retainedEarnings = $this->computeRetainedEarnings($fiscalStart, $asOf);

        $totalEquity = $totalEquityBooked + $retainedEarnings;
        $totalLE = $totalLiabilities + $totalEquity;
        $delta = $totalAssets - $totalLE;

        return [
            'asOf' => $asOfCarbon->format('d M Y'),
            'fiscalStart' => Carbon::parse($fiscalStart)->format('d M Y'),
            'assetsRows' => $assetsRows,
            'contraAssetsRows' => $contraAssetsRows,
            'liabilitiesRows' => $liabilitiesRows,
            'contraLiabilitiesRows' => $contraLiabilitiesRows,
            'equityRows' => $equityRows,
            'totalAssetsGross' => $totalAssetsGross,
            'totalContraAssets' => $totalContraAssets,
            'totalAssets' => $totalAssets,
            'totalLiabilitiesGross' => $totalLiabilitiesGross,
            'totalContraLiabilities' => $totalContraLiabilities,
            'totalLiabilities' => $totalLiabilities,
            'totalEquityBooked' => $totalEquityBooked,
            'retainedEarnings' => $retainedEarnings,
            'totalEquity' => $totalEquity,
            'totalLE' => $totalLE,
            'delta' => $delta,
            'balanced' => abs($delta) < 0.01,
        ];
    }

    /**
     * Net Income for the period = revenue − contra_revenue − cogs − expense,
     * each balance taken from fiscal-year-start through $to.
     */
    protected function computeRetainedEarnings(string $from, string $to): float
    {
        $sum = function (array $types) use ($from, $to): array {
            $row = DB::table('journal_entry_lines as l')
                ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
                ->where('e.status', JournalEntry::STATUS_POSTED)
                ->whereBetween('e.entry_date', [$from, $to])
                ->whereIn('a.type', $types)
                ->select(
                    DB::raw('SUM(l.debit) as debit_sum'),
                    DB::raw('SUM(l.credit) as credit_sum'),
                )
                ->first();
            $debit = (float) ($row->debit_sum ?? 0);
            $credit = (float) ($row->credit_sum ?? 0);

            // Caller does sign math based on natural direction.
            return [$debit, $credit];
        };

        [$revD, $revC] = $sum([Account::TYPE_REVENUE]);
        $revenue = $revC - $revD; // credit-normal

        [$crD, $crC] = $sum([Account::TYPE_CONTRA_REVENUE]);
        $contraRevenue = $crD - $crC; // debit-normal

        [$cogsD, $cogsC] = $sum([Account::TYPE_COGS]);
        $cogs = $cogsD - $cogsC; // debit-normal

        [$expD, $expC] = $sum([Account::TYPE_EXPENSE]);
        $expense = $expD - $expC; // debit-normal

        return $revenue - $contraRevenue - $cogs - $expense;
    }
}
