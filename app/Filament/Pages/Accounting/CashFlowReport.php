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
 * Cash Flow Statement (simplified indirect method).
 *
 * Operating  = Net Income + ΔLiabilities (AP, Doctor Payable) − ΔReceivables − ΔInventory
 * Investing  = −ΔFixed Assets
 * Financing  = ΔOwner Capital
 *
 * The verification row checks that Cash@start + Net Change == Cash@end.
 */
class CashFlowReport extends Page implements HasForms
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
            ['heading' => __('help.pages.cash_flow_report.what.heading'), 'body' => __('help.pages.cash_flow_report.what.body')],
            ['heading' => __('help.pages.cash_flow_report.how.heading'), 'items' => (array) trans('help.pages.cash_flow_report.how.items')],
            ['heading' => __('help.pages.cash_flow_report.faq.heading'), 'items' => (array) trans('help.pages.cash_flow_report.faq.items')],
        ];
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 80;

    protected static string $view = 'filament.pages.accounting.cash-flow-report';

    public ?array $filters = [];

    public static function getNavigationGroup(): ?string
    {
        return __('common.nav.accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.cash_flow_report.nav_label');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('pages.cash_flow_report.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('view_accounting_cash_flow');
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
     * Sum of natural-direction balances for accounts matched by a code-prefix list,
     * as of a given date.
     */
    protected function balanceAtByCodes(array $codePrefixes, string $asOf): float
    {
        $q = Account::query();
        $q->where(function ($w) use ($codePrefixes) {
            foreach ($codePrefixes as $p) {
                $w->orWhere('code', 'like', $p.'%');
            }
        });
        $accountIds = $q->pluck('id')->all();
        if (empty($accountIds)) {
            return 0.0;
        }

        return $this->balanceAtForIds($accountIds, $asOf);
    }

    /**
     * Net balance (natural direction) for the supplied account ids, as of date.
     */
    protected function balanceAtForIds(array $accountIds, string $asOf): float
    {
        if (empty($accountIds)) {
            return 0.0;
        }

        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereDate('e.entry_date', '<=', $asOf)
            ->whereIn('a.id', $accountIds)
            ->groupBy('a.id', 'a.type')
            ->select(
                'a.id',
                'a.type',
                DB::raw('SUM(l.debit) as debit_sum'),
                DB::raw('SUM(l.credit) as credit_sum'),
            )
            ->get();

        $total = 0.0;
        foreach ($rows as $r) {
            $debit = (float) $r->debit_sum;
            $credit = (float) $r->credit_sum;
            $isDebitNormal = in_array($r->type, Account::DEBIT_NORMAL_TYPES, true);
            $total += $isDebitNormal ? $debit - $credit : $credit - $debit;
        }

        return $total;
    }

    /** Net Income for a period (P&L bottom line). */
    protected function netIncome(string $from, string $to): float
    {
        $sumTypes = function (array $types) use ($from, $to): array {
            $r = DB::table('journal_entry_lines as l')
                ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
                ->where('e.status', JournalEntry::STATUS_POSTED)
                ->whereBetween('e.entry_date', [$from, $to])
                ->whereIn('a.type', $types)
                ->select(
                    DB::raw('SUM(l.debit) as d'),
                    DB::raw('SUM(l.credit) as c'),
                )
                ->first();

            return [(float) ($r->d ?? 0), (float) ($r->c ?? 0)];
        };
        [$rD, $rC] = $sumTypes([Account::TYPE_REVENUE]);
        [$crD, $crC] = $sumTypes([Account::TYPE_CONTRA_REVENUE]);
        [$coD, $coC] = $sumTypes([Account::TYPE_COGS]);
        [$eD, $eC] = $sumTypes([Account::TYPE_EXPENSE]);

        $revenue = $rC - $rD;
        $contraRevenue = $crD - $crC;
        $cogs = $coD - $coC;
        $expense = $eD - $eC;

        return $revenue - $contraRevenue - $cogs - $expense;
    }

    /**
     * Δ balance for a set of account ids = balance_at(to) − balance_at(from_minus_1).
     * We use $from - 1 day as the "opening" point so changes during [from..to] are captured.
     */
    protected function deltaForIds(array $accountIds, string $from, string $to): float
    {
        $openingAsOf = Carbon::parse($from)->subDay()->toDateString();
        $end = $this->balanceAtForIds($accountIds, $to);
        $start = $this->balanceAtForIds($accountIds, $openingAsOf);

        return $end - $start;
    }

    /** Δ for accounts matched by code prefixes. */
    protected function deltaForCodes(array $codePrefixes, string $from, string $to): float
    {
        $ids = Account::query()
            ->where(function ($w) use ($codePrefixes) {
                foreach ($codePrefixes as $p) {
                    $w->orWhere('code', 'like', $p.'%');
                }
            })
            ->pluck('id')->all();

        return $this->deltaForIds($ids, $from, $to);
    }

    /** Cash @ a date = sum of all 1010* and 1020* accounts (Cash on Hand + Bank). */
    protected function cashAt(string $asOf): float
    {
        return $this->balanceAtByCodes(['1010', '1020'], $asOf);
    }

    public function getViewData(): array
    {
        $from = $this->filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['to'] ?? now()->toDateString();

        $netIncome = $this->netIncome($from, $to);

        // Working-capital deltas.
        $deltaAP = $this->deltaForCodes(['2010'], $from, $to);              // Accounts Payable
        $deltaDoctorPayable = $this->deltaForCodes(['2020'], $from, $to);   // Doctor Payable
        $deltaAR = $this->deltaForCodes(['1100', '1110', '1120'], $from, $to); // AR (parent + children)
        $deltaInventory = $this->deltaForCodes(['1200'], $from, $to);        // Inventory

        // Operating: add liability increases, subtract asset increases.
        $cashFromOps = $netIncome
            + $deltaAP
            + $deltaDoctorPayable
            - $deltaAR
            - $deltaInventory;

        // Investing: Fixed Assets purchases (asset increase) reduce cash.
        // Accumulated depreciation is contra-asset; not a cash item.
        $deltaFixedAssets = $this->deltaForCodes(['1400'], $from, $to);
        $cashFromInvesting = -$deltaFixedAssets;

        // Financing: Owner Capital increases add cash.
        $deltaOwnerCapital = $this->deltaForCodes(['3010'], $from, $to);
        $cashFromFinancing = $deltaOwnerCapital;

        $netChange = $cashFromOps + $cashFromInvesting + $cashFromFinancing;

        $cashStart = $this->cashAt(Carbon::parse($from)->subDay()->toDateString());
        $cashEnd = $this->cashAt($to);
        $cashEndComputed = $cashStart + $netChange;
        $verificationDelta = $cashEnd - $cashEndComputed;
        $reconciles = abs($verificationDelta) < 0.01;

        return [
            'from' => Carbon::parse($from)->format('d M Y'),
            'to' => Carbon::parse($to)->format('d M Y'),
            'netIncome' => $netIncome,
            'deltaAP' => $deltaAP,
            'deltaDoctorPayable' => $deltaDoctorPayable,
            'deltaAR' => $deltaAR,
            'deltaInventory' => $deltaInventory,
            'cashFromOps' => $cashFromOps,
            'deltaFixedAssets' => $deltaFixedAssets,
            'cashFromInvesting' => $cashFromInvesting,
            'deltaOwnerCapital' => $deltaOwnerCapital,
            'cashFromFinancing' => $cashFromFinancing,
            'netChange' => $netChange,
            'cashStart' => $cashStart,
            'cashEnd' => $cashEnd,
            'cashEndComputed' => $cashEndComputed,
            'verificationDelta' => $verificationDelta,
            'reconciles' => $reconciles,
        ];
    }
}
