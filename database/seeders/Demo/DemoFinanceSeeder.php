<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankStatementLine;
use App\Models\Accounting\Expense;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\PrepaidSchedule;
use App\Models\Accounting\Vendor;
use App\Models\Branch;
use App\Models\User;
use App\Services\Accounting\DepreciationService;
use App\Services\Accounting\PrepaymentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Gives the financial statements a believable cost side.
 *
 * Before this runs the P&L has revenue, COGS and doctor commission but exactly
 * one operating expense, no fixed assets and no prepayments — so net profit is
 * wildly overstated, the Balance Sheet has no non-current assets, and
 * depreciation/amortization are zero. This seeds:
 *   - recurring + one-off operating expenses, posted to the GL
 *   - fixed assets with monthly depreciation runs
 *   - prepaid schedules with monthly amortization runs
 *   - a bank reconciliation with statement lines to reconcile
 */
class DemoFinanceSeeder extends Seeder
{
    /** How many months of cost history to build. */
    protected int $months = 5;

    /**
     * Capital intensity, tuned against what the estate actually bills.
     *
     * The catalogue below is priced for a flagship aesthetic clinic. Applying it
     * unscaled to all twelve branches produced a depreciation charge of ~14k
     * KWD/month against ~107k KWD/month of revenue — 13% of turnover, roughly
     * triple what a leased clinic group really carries. This scales the asset
     * register (and therefore the depreciation) down to ~4% of revenue.
     */
    protected float $assetScale = 0.55;

    /** Months of depreciation/amortization catch-up to post. */
    protected int $catchUpMonths = 5;

    /**
     * Recurring monthly costs, per branch: [account code, label, base amount, ±variance].
     */
    protected array $recurring = [
        ['6210', 'Monthly clinic rent', 1450.000, 0.0],
        ['6220', 'Electricity & water', 186.500, 0.25],
        ['6440', 'Cleaning contract', 120.000, 0.0],
        ['6410', 'Telephone & internet', 68.000, 0.1],
        ['6520', 'Bank charges & KNET fees', 74.250, 0.35],
    ];

    /**
     * Occasional costs — drawn a few per month so the expense mix isn't flat.
     *
     * Deliberately excludes insurance, MOH licences and the ERP licence: those
     * are paid annually up front and released through the prepaid schedules
     * below, so booking them here as well would double-count the same cost.
     */
    protected array $occasional = [
        ['6310', 'Instagram & TikTok campaign', 320.000, 0.5],
        ['6320', 'Google Ads — branded search', 210.000, 0.4],
        ['6330', 'Influencer collaboration', 450.000, 0.6],
        ['6430', 'Printing & stationery', 62.500, 0.4],
        ['6230', 'Building maintenance & AC service', 175.000, 0.5],
        ['6480', 'Legal & contract consultation', 300.000, 0.3],
        ['6490', 'External audit fees', 550.000, 0.2],
        ['6140', 'Staff visa & residency renewals', 190.000, 0.4],
        ['6160', 'Staff hospitality & pantry', 55.000, 0.5],
        ['6530', 'Miscellaneous clinic expenses', 48.000, 0.6],
    ];

    /**
     * Fixed-asset catalogue: [category, name, cost, life months, asset code, accum code].
     */
    protected array $assets = [
        ['Medical Equipment', 'Candela GentleMax Pro laser platform', 24500.000, 84, '1210', '1215'],
        ['Medical Equipment', 'Alma Harmony XL Pro', 18900.000, 84, '1210', '1215'],
        ['Medical Equipment', 'HydraFacial Syndeo unit', 9800.000, 60, '1210', '1215'],
        ['Medical Equipment', 'Ultraformer III HIFU device', 14200.000, 72, '1210', '1215'],
        ['Medical Equipment', 'Autoclave steriliser (Class B)', 3400.000, 60, '1210', '1215'],
        ['Medical Equipment', 'Dermatoscope & imaging system', 2750.000, 48, '1210', '1215'],
        ['Furniture & Fixtures', 'Treatment chairs & couches (set of 6)', 5600.000, 60, '1220', '1225'],
        ['Furniture & Fixtures', 'Reception desk & waiting-area fit-out', 4200.000, 60, '1220', '1225'],
        ['Furniture & Fixtures', 'Consultation room furniture', 3100.000, 60, '1220', '1225'],
        ['Computers & IT', 'Reception & consultation workstations', 3850.000, 36, '1230', '1235'],
        ['Computers & IT', 'Network, Wi-Fi & CCTV infrastructure', 2900.000, 48, '1230', '1235'],
        ['Computers & IT', 'Tablets for clinical notes', 1450.000, 36, '1230', '1235'],
        ['Leasehold Improvements', 'Clinic fit-out & partitioning', 32000.000, 120, '1240', '1245'],
        ['Leasehold Improvements', 'Signage & external branding', 4800.000, 60, '1240', '1245'],
    ];

    public function run(): void
    {
        $branches = Branch::query()->orderBy('id')->get(['id']);
        if ($branches->isEmpty()) {
            $this->command?->warn('DemoFinanceSeeder: no branches.');

            return;
        }

        $accountant = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'accountant'))->value('id')
            ?? User::query()->value('id');

        $this->ensurePeriods();
        $this->seedExpenses($branches, $accountant);
        $this->seedFixedAssets($branches, $accountant);
        $this->seedPrepayments($branches, $accountant);
        $this->runMonthlyRuns($accountant);
        $this->seedBankReconciliations($accountant);
    }

    /** Depreciation/amortization refuse to post into a month with no period row. */
    protected function ensurePeriods(): void
    {
        $cursor = Carbon::today()->startOfMonth()->subMonths($this->months + 1);
        $stop = Carbon::today()->startOfMonth();

        while ($cursor->lte($stop)) {
            AccountingPeriod::query()->firstOrCreate(
                ['code' => $cursor->format('Y-m')],
                [
                    'start_date' => $cursor->copy()->startOfMonth()->toDateString(),
                    'end_date' => $cursor->copy()->endOfMonth()->toDateString(),
                    'status' => 'open',
                ],
            );
            $cursor->addMonth();
        }
    }

    protected function seedExpenses($branches, ?int $userId): void
    {
        if (Expense::query()->count() > 20) {
            $this->command?->warn('DemoFinanceSeeder: expenses already seeded — skipping.');

            return;
        }

        $vendors = Vendor::query()->pluck('id')->all();
        $cash = $this->account('1110');
        $bank = $this->account('1120');
        if (! $cash && ! $bank) {
            $this->command?->warn('DemoFinanceSeeder: no cash/bank account — skipping expenses.');

            return;
        }

        $created = 0;
        $posted = 0;

        for ($m = $this->months; $m >= 0; $m--) {
            $month = Carbon::today()->startOfMonth()->subMonths($m);
            // The current month is only partly elapsed — don't bill a full month of costs.
            $partial = $month->isSameMonth(Carbon::today());

            foreach ($branches as $bi => $branch) {
                foreach ($this->recurring as $ri => [$code, $label, $base, $variance]) {
                    if ($partial && Carbon::today()->day < 5) {
                        continue;
                    }
                    $account = $this->account($code);
                    if (! $account) {
                        continue;
                    }
                    $expense = $this->makeExpense(
                        $branch->id,
                        $account->id,
                        ($ri % 2 === 0 ? $bank : $cash)?->id,
                        $this->jitter($base, $variance),
                        $label,
                        $month->copy()->addDays(min(2 + $ri, $month->daysInMonth - 1)),
                        $vendors ? $vendors[($bi + $ri) % count($vendors)] : null,
                        $userId,
                    );
                    $created++;
                    $posted += $this->post($expense, $userId);
                }

                // Two occasional costs per branch per month, rotated so each
                // category shows up somewhere across the estate.
                for ($k = 0; $k < 2; $k++) {
                    $pick = $this->occasional[($bi * 2 + $k + $m) % count($this->occasional)];
                    [$code, $label, $base, $variance] = $pick;
                    $account = $this->account($code);
                    if (! $account) {
                        continue;
                    }
                    $day = min(random_int(6, 26), $partial ? max(1, Carbon::today()->day) : $month->daysInMonth);
                    $expense = $this->makeExpense(
                        $branch->id,
                        $account->id,
                        ($k === 0 ? $bank : $cash)?->id,
                        $this->jitter($base, $variance),
                        $label,
                        $month->copy()->addDays($day - 1),
                        $vendors ? $vendors[($bi + $k) % count($vendors)] : null,
                        $userId,
                    );
                    $created++;
                    // A handful stay in draft so the Expenses screen has a real
                    // pending queue rather than a fully-posted wall.
                    if (($created % 11) !== 0) {
                        $posted += $this->post($expense, $userId);
                    }
                }
            }
        }

        $this->command?->info("DemoFinanceSeeder: created {$created} expenses ({$posted} posted to the GL).");
    }

    protected function makeExpense(int $branchId, int $accountId, ?int $paymentAccountId, float $amount, string $description, Carbon $date, ?int $vendorId, ?int $userId): Expense
    {
        return Expense::create([
            'expense_date' => $date->toDateString(),
            'vendor_id' => $vendorId,
            'branch_id' => $branchId,
            'account_id' => $accountId,
            'payment_account_id' => $paymentAccountId,
            'amount' => $amount,
            'description' => $description,
            'reference_no' => 'INV-'.$date->format('ym').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => Expense::STATUS_DRAFT,
            'created_at' => $date->copy()->setTime(11, 0),
            'updated_at' => $date->copy()->setTime(11, 0),
        ]);
    }

    protected function post(Expense $expense, ?int $userId): int
    {
        try {
            $expense->post($userId);

            return $expense->refresh()->status === Expense::STATUS_POSTED ? 1 : 0;
        } catch (\Throwable $e) {
            $this->command?->warn("  expense #{$expense->id} did not post: {$e->getMessage()}");

            return 0;
        }
    }

    protected function seedFixedAssets($branches, ?int $userId): void
    {
        if (FixedAsset::query()->exists()) {
            $this->command?->warn('DemoFinanceSeeder: fixed assets already seeded — skipping.');

            return;
        }

        $expenseAccount = $this->account('6610');
        $created = 0;

        foreach ($branches as $bi => $branch) {
            // Only the flagship branch of each group runs the full device
            // catalogue; the rest carry a couple of platforms plus fit-out and
            // IT. Giving every branch everything put the depreciation charge
            // far above what the estate actually bills.
            $slice = $bi % 3 === 0
                ? array_merge(array_slice($this->assets, 0, 3), array_slice($this->assets, 6))
                : array_slice($this->assets, 6);

            foreach ($slice as $ai => [$category, $name, $cost, $life, $assetCode, $accumCode]) {
                $asset = $this->account($assetCode);
                $accum = $this->account($accumCode);
                if (! $asset || ! $accum) {
                    continue;
                }

                // Staggered in-service dates so the depreciation schedule isn't
                // a single cliff and the asset register shows varying NBV.
                $inService = Carbon::today()->startOfMonth()->subMonths(($bi + $ai) % 30 + 2)->addDays(random_int(0, 20));

                FixedAsset::create([
                    'code' => 'FA-'.str_pad((string) ($branch->id), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) ($ai + 1), 3, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'category' => $category,
                    'branch_id' => $branch->id,
                    'asset_account_id' => $asset->id,
                    'accumulated_depreciation_account_id' => $accum->id,
                    'depreciation_expense_account_id' => $expenseAccount?->id,
                    'cost' => $this->jitter($cost * $this->assetScale, 0.08),
                    'salvage_value' => round($cost * $this->assetScale * 0.05, 3),
                    'useful_life_months' => $life,
                    'in_service_date' => $inService->toDateString(),
                    'method' => 'straight_line',
                    'status' => FixedAsset::STATUS_ACTIVE,
                    'accumulated_depreciation' => 0,
                    'created_by_user_id' => $userId,
                    'created_at' => $inService,
                    'updated_at' => $inService,
                ]);
                $created++;
            }
        }

        $this->command?->info("DemoFinanceSeeder: created {$created} fixed assets.");
    }

    protected function seedPrepayments($branches, ?int $userId): void
    {
        if (PrepaidSchedule::query()->exists()) {
            $this->command?->warn('DemoFinanceSeeder: prepaid schedules already seeded — skipping.');

            return;
        }

        // [prepaid asset code, expense code, name, annual amount, term months]
        //
        // Rent is deliberately not here — it is paid monthly and already booked
        // as a recurring expense; releasing it from a prepaid schedule too would
        // charge the same rent twice.
        $templates = [
            ['1170', '6510', 'Medical malpractice insurance — annual policy', 2400.000, 12],
            ['1170', '6420', 'ERP & practice-management licence (annual)', 1560.000, 12],
            ['1170', '6500', 'MOH facility licence (2-year)', 1800.000, 24],
        ];

        $created = 0;
        foreach ($branches as $bi => $branch) {
            foreach ($templates as $ti => [$prepaidCode, $expenseCode, $name, $total, $term]) {
                // Insurance is estate-wide; the licences sit on a few branches
                // so the schedule list isn't uniform.
                if ($ti >= 1 && $bi % 3 !== 0) {
                    continue;
                }
                $prepaid = $this->account($prepaidCode);
                $expense = $this->account($expenseCode);
                if (! $prepaid || ! $expense) {
                    continue;
                }

                $start = Carbon::today()->startOfMonth()->subMonths(($bi % 6) + 3);

                PrepaidSchedule::create([
                    'code' => 'PRE-'.str_pad((string) $branch->id, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) ($ti + 1), 3, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'branch_id' => $branch->id,
                    'prepaid_account_id' => $prepaid->id,
                    'expense_account_id' => $expense->id,
                    'total_amount' => $this->jitter($total, 0.06),
                    'term_months' => $term,
                    'start_date' => $start->toDateString(),
                    'status' => PrepaidSchedule::STATUS_ACTIVE,
                    'amortized_amount' => 0,
                    'created_by_user_id' => $userId,
                    'created_at' => $start,
                    'updated_at' => $start,
                ]);
                $created++;
            }
        }

        $this->command?->info("DemoFinanceSeeder: created {$created} prepaid schedules.");
    }

    /** Catch the depreciation + amortization ledgers up, month by month. */
    protected function runMonthlyRuns(?int $userId): void
    {
        $depreciation = app(DepreciationService::class);
        $prepayment = app(PrepaymentService::class);

        $depTotal = 0.0;
        $amortTotal = 0.0;

        // Reach back over the trading history so assets carry real accumulated
        // depreciation on the Balance Sheet, without charging years of it
        // against six months of revenue.
        for ($m = $this->catchUpMonths; $m >= 0; $m--) {
            $month = Carbon::today()->startOfMonth()->subMonths($m);
            if ($month->isSameMonth(Carbon::today()) && Carbon::today()->day < Carbon::today()->daysInMonth) {
                continue; // don't depreciate an unfinished month
            }
            $depTotal += (float) ($depreciation->runForMonth($month->copy(), $userId)['total'] ?? 0);
            $amortTotal += (float) ($prepayment->runForMonth($month->copy(), $userId)['total'] ?? 0);
        }

        $this->command?->info('DemoFinanceSeeder: posted '.number_format($depTotal, 3).' KWD depreciation and '.number_format($amortTotal, 3).' KWD amortization.');
    }

    /**
     * A completed reconciliation for last month and an in-progress one for this
     * month, with statement lines mirroring the bank postings (a couple left
     * unmatched so the screen has work to show).
     */
    protected function seedBankReconciliations(?int $userId): void
    {
        if (BankReconciliation::query()->exists()) {
            $this->command?->warn('DemoFinanceSeeder: bank reconciliations already seeded — skipping.');

            return;
        }

        $bank = $this->account('1120');
        if (! $bank) {
            return;
        }

        foreach ([1, 0] as $offset) {
            $start = Carbon::today()->startOfMonth()->subMonths($offset);
            $end = $offset === 0 ? Carbon::today() : $start->copy()->endOfMonth();

            $lines = JournalEntryLine::query()
                ->where('account_id', $bank->id)
                ->whereHas('entry', fn ($q) => $q->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()]))
                ->with('entry')
                ->orderBy('id')
                ->limit(60)
                ->get();

            $opening = (float) JournalEntryLine::query()
                ->where('account_id', $bank->id)
                ->whereHas('entry', fn ($q) => $q->where('entry_date', '<', $start->toDateString()))
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as bal')
                ->value('bal');
            $movement = (float) $lines->sum(fn ($l) => (float) $l->debit - (float) $l->credit);

            $rec = BankReconciliation::create([
                'code' => 'BR-'.$start->format('Ym'),
                'account_id' => $bank->id,
                'period_start' => $start->copy()->startOfMonth()->toDateString(),
                'period_end' => $end->toDateString(),
                'opening_balance' => round($opening, 3),
                'closing_balance' => round($opening + $movement, 3),
                'book_opening_balance' => round($opening, 3),
                'book_closing_balance' => round($opening + $movement, 3),
                'status' => $offset === 1 ? 'completed' : 'in_progress',
                'completed_at' => $offset === 1 ? $end->copy()->addDays(3)->setTime(14, 0) : null,
                'completed_by_user_id' => $offset === 1 ? $userId : null,
                'notes' => $offset === 1 ? 'Reconciled against the CBK statement; no differences.' : 'Statement imported, matching in progress.',
                'created_at' => $end,
                'updated_at' => $end,
            ]);

            $i = 0;
            foreach ($lines as $line) {
                $i++;
                // Leave every 9th line unmatched on the open reconciliation so
                // the "unmatched" column isn't empty.
                $matched = $offset === 1 || $i % 9 !== 0;
                BankStatementLine::create([
                    'bank_reconciliation_id' => $rec->id,
                    'statement_date' => $line->entry?->entry_date ?? $start->toDateString(),
                    'description' => $line->description ?: ($line->entry?->narration ?? 'Bank movement'),
                    'reference' => 'STM'.$start->format('ym').str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'matched_journal_entry_line_id' => $matched ? $line->id : null,
                    'matched_at' => $matched ? $end->copy()->addDays(2) : null,
                    'matched_by_user_id' => $matched ? $userId : null,
                ]);
            }

            // Bank-only items that never hit the books — the classic reconciling
            // difference a reviewer expects to see.
            foreach ([['Account maintenance charge', 0, 5.500], ['Interest credited', 3.250, 0]] as $k => [$desc, $debit, $credit]) {
                BankStatementLine::create([
                    'bank_reconciliation_id' => $rec->id,
                    'statement_date' => $end->copy()->subDays($k)->toDateString(),
                    'description' => $desc,
                    'reference' => 'STM'.$start->format('ym').'X'.$k,
                    'debit' => $debit,
                    'credit' => $credit,
                    'notes' => 'Bank-only item — not yet booked.',
                ]);
            }
        }

        $this->command?->info('DemoFinanceSeeder: created 2 bank reconciliations with statement lines.');
    }

    protected function account(string $code): ?Account
    {
        static $cache = [];

        return $cache[$code] ??= Account::query()->where('code', $code)->first();
    }

    protected function jitter(float $base, float $variance): float
    {
        if ($variance <= 0) {
            return round($base, 3);
        }
        $factor = 1 + ((random_int(-100, 100) / 100) * $variance);

        return round(max(1, $base * $factor), 3);
    }
}
