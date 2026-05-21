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
 * Profit & Loss (Income Statement).
 *
 * For a chosen date range, walks the chart of accounts and reports:
 *   Revenue − Contra-Revenue        = Net Revenue
 *   Net Revenue − COGS              = Gross Profit
 *   Gross Profit − Operating Exp    = Net Profit
 *
 * Sub-accounts are rolled up under their parent so the user sees the
 * hierarchy as it exists in the Chart of Accounts.
 */
class ProfitAndLossReport extends Page implements HasForms
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
            ['heading' => __('help.pages.profit_and_loss_report.what.heading'), 'body' => __('help.pages.profit_and_loss_report.what.body')],
            ['heading' => __('help.pages.profit_and_loss_report.how.heading'), 'items' => (array) trans('help.pages.profit_and_loss_report.how.items')],
            ['heading' => __('help.pages.profit_and_loss_report.faq.heading'), 'items' => (array) trans('help.pages.profit_and_loss_report.faq.items')],
        ];
    }

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 60;

    protected static string $view = 'filament.pages.accounting.profit-and-loss-report';

    public ?array $filters = [];

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.profit_and_loss_report.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.profit_and_loss_report.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('view_accounting_profit_and_loss');
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

    /**
     * Build a section: every account of the given type(s), with hierarchy.
     * Returns ['rows' => [...accounts with depth/parent], 'total' => sum].
     */
    protected function buildSection(array $types, string $from, string $to): array
    {
        // Map of account_id => net balance (in natural direction) for the period,
        // computed in a single query.
        $balances = $this->balancesForTypes($types, $from, $to);

        // Pull every account of these types, plus any ancestor that might roll up.
        $accounts = Account::query()
            ->whereIn('type', $types)
            ->orderBy('code')
            ->get();

        // Roll-up: for each parent that is itself in this section,
        // its displayed total = own balance + sum of descendants.
        $byId = $accounts->keyBy('id');
        $rolled = [];
        foreach ($accounts as $a) {
            $rolled[$a->id] = (float) ($balances[$a->id] ?? 0.0);
        }
        // Add descendants up the chain.
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

        // Build a depth-aware row list, ordered by hierarchy (parent first then children).
        $rows = [];
        $emit = function ($node, $depth) use (&$emit, $accounts, $rolled, $balances) {
            $own = (float) ($balances[$node->id] ?? 0.0);
            $rollup = (float) ($rolled[$node->id] ?? 0.0);
            // Skip lines with no own activity AND no rolled-up activity to keep the report tidy.
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
        // Roots = accounts in this section whose parent isn't also in this section.
        foreach ($accounts as $a) {
            if (! $a->parent_id || ! isset($byId[$a->parent_id])) {
                $emit($a, 0);
            }
        }

        $total = array_sum($balances);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Single query: per-account net balance (natural direction) for the period.
     * Returns [account_id => float].
     */
    protected function balancesForTypes(array $types, string $from, string $to): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
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

    public function getViewData(): array
    {
        $from = $this->filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['to'] ?? now()->toDateString();

        $revenue = $this->buildSection([Account::TYPE_REVENUE], $from, $to);
        $contraRevenue = $this->buildSection([Account::TYPE_CONTRA_REVENUE], $from, $to);
        $cogs = $this->buildSection([Account::TYPE_COGS], $from, $to);
        $expenses = $this->buildSection([Account::TYPE_EXPENSE], $from, $to);

        $netRevenue = $revenue['total'] - $contraRevenue['total'];
        $grossProfit = $netRevenue - $cogs['total'];
        $netProfit = $grossProfit - $expenses['total'];

        return [
            'from' => Carbon::parse($from)->format('d M Y'),
            'to' => Carbon::parse($to)->format('d M Y'),
            'revenue' => $revenue,
            'contraRevenue' => $contraRevenue,
            'cogs' => $cogs,
            'expenses' => $expenses,
            'netRevenue' => $netRevenue,
            'grossProfit' => $grossProfit,
            'netProfit' => $netProfit,
        ];
    }
}
