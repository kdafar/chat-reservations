<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only financial-statement builders for the v2 reports. The query logic is
 * ported verbatim from the Filament report pages (TrialBalance, GeneralLedger,
 * ProfitAndLossReport, BalanceSheetReport, CashFlowReport) so the numbers match
 * the legacy admin exactly — only the presentation layer changed.
 *
 * Every figure is computed from POSTED journal entries; "natural direction"
 * means debit-normal accounts are debit−credit and credit-normal are credit−debit.
 */
class AccountingReportService
{
    // ---------------------------------------------------------------------
    // Trial Balance
    // ---------------------------------------------------------------------
    public function trialBalance(string $from, string $to): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->select('a.code', 'a.name', 'a.type', DB::raw('SUM(l.debit) as debit_sum'), DB::raw('SUM(l.credit) as credit_sum'))
            ->get();

        $out = $rows->map(function ($r) {
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
            ];
        })->all();

        $totalDebit = array_sum(array_column($out, 'debit'));
        $totalCredit = array_sum(array_column($out, 'credit'));

        return [
            'rows' => $out,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    // ---------------------------------------------------------------------
    // General Ledger (per account, running balance)
    // ---------------------------------------------------------------------
    public function generalLedger(?int $accountId, string $from, string $to, ?int $branchId): array
    {
        $empty = [
            'account' => null, 'rows' => [], 'opening_balance' => 0.0,
            'closing_balance' => 0.0, 'period_activity' => 0.0, 'branch' => null,
            'total_debit' => 0.0, 'total_credit' => 0.0, 'entry_count' => 0, 'line_count' => 0, 'currency' => 'KWD',
        ];

        if (! $accountId) {
            return $empty;
        }
        $account = Account::find($accountId);
        if (! $account) {
            return $empty;
        }

        $openingBalance = $account->balanceAt(Carbon::parse($from)->subDay()->toDateString());

        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->leftJoin('doctors as d', 'd.id', '=', 'l.doctor_id')
            ->leftJoin('patients as p', 'p.id', '=', 'l.patient_id')
            ->where('l.account_id', $account->id)
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')
            ->select(
                'l.id as line_id', 'l.debit', 'l.credit', 'l.description as line_description',
                'l.branch_id', 'e.id as je_id', 'e.code as je_code', 'e.entry_date',
                'e.narration', 'e.source_type', 'e.source_id', 'd.name as doctor_name', 'p.name as patient_name',
            );

        if ($branchId) {
            $query->where('l.branch_id', $branchId);
        }

        $raw = $query->get();

        $branchIds = $raw->pluck('branch_id')->filter()->unique()->all();
        $branchNames = $branchIds
            ? Branch::query()->whereIn('id', $branchIds)->get()->mapWithKeys(fn ($b) => [$b->id => (string) $b->localized_name])->all()
            : [];

        $isDebitNormal = $account->isDebitNormal();
        $balance = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $rows = [];
        foreach ($raw as $r) {
            $debit = (float) $r->debit;
            $credit = (float) $r->credit;
            $totalDebit += $debit;
            $totalCredit += $credit;
            $balance += $isDebitNormal ? ($debit - $credit) : ($credit - $debit);

            $rows[] = [
                'je_id' => $r->je_id,
                'je_code' => $r->je_code,
                'entry_date' => $r->entry_date,
                'description' => $r->line_description ?: $r->narration,
                'source_label' => ($r->source_type && $r->source_id) ? class_basename($r->source_type).' #'.$r->source_id : null,
                'branch_name' => $r->branch_id ? ($branchNames[$r->branch_id] ?? null) : null,
                'doctor_name' => $r->doctor_name,
                'patient_name' => $r->patient_name,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $balance,
            ];
        }

        return [
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'is_debit_normal' => $isDebitNormal],
            'rows' => $rows,
            'opening_balance' => $openingBalance,
            'closing_balance' => $balance,
            'period_activity' => $balance - $openingBalance,
            'branch' => $branchId ? (Branch::find($branchId)?->localized_name) : null,
            'total_debit' => round($totalDebit, 3),
            'total_credit' => round($totalCredit, 3),
            'entry_count' => $raw->pluck('je_id')->unique()->count(),
            'line_count' => count($rows),
            'currency' => $account->currency ?: 'KWD',
        ];
    }

    // ---------------------------------------------------------------------
    // Profit & Loss
    // ---------------------------------------------------------------------
    public function profitAndLoss(string $from, string $to): array
    {
        $revenue = $this->section([Account::TYPE_REVENUE], $this->balancesForTypes([Account::TYPE_REVENUE], $from, $to));
        $contraRevenue = $this->section([Account::TYPE_CONTRA_REVENUE], $this->balancesForTypes([Account::TYPE_CONTRA_REVENUE], $from, $to));
        $cogs = $this->section([Account::TYPE_COGS], $this->balancesForTypes([Account::TYPE_COGS], $from, $to));
        $expenses = $this->section([Account::TYPE_EXPENSE], $this->balancesForTypes([Account::TYPE_EXPENSE], $from, $to));

        $netRevenue = $revenue['total'] - $contraRevenue['total'];
        $grossProfit = $netRevenue - $cogs['total'];
        $netProfit = $grossProfit - $expenses['total'];

        return compact('revenue', 'contraRevenue', 'cogs', 'expenses', 'netRevenue', 'grossProfit', 'netProfit');
    }

    // ---------------------------------------------------------------------
    // Balance Sheet (as of a date)
    // ---------------------------------------------------------------------
    public function balanceSheet(string $asOf): array
    {
        $assetBalances = $this->balancesAt([Account::TYPE_ASSET], $asOf);
        $contraAssetBalances = $this->balancesAt([Account::TYPE_CONTRA_ASSET], $asOf);
        $liabilityBalances = $this->balancesAt([Account::TYPE_LIABILITY], $asOf);
        $contraLiabilityBalances = $this->balancesAt([Account::TYPE_CONTRA_LIABILITY], $asOf);
        $equityBalances = $this->balancesAt([Account::TYPE_EQUITY], $asOf);

        $totalAssetsGross = array_sum($assetBalances);
        $totalContraAssets = array_sum($contraAssetBalances);
        $totalAssets = $totalAssetsGross - $totalContraAssets;

        $totalLiabilitiesGross = array_sum($liabilityBalances);
        $totalContraLiabilities = array_sum($contraLiabilityBalances);
        $totalLiabilities = $totalLiabilitiesGross - $totalContraLiabilities;

        $totalEquityBooked = array_sum($equityBalances);
        $fiscalStart = Carbon::parse($asOf)->startOfYear()->toDateString();
        $retainedEarnings = $this->netIncome($fiscalStart, $asOf);

        $totalEquity = $totalEquityBooked + $retainedEarnings;
        $totalLE = $totalLiabilities + $totalEquity;
        $delta = $totalAssets - $totalLE;

        return [
            'fiscal_start' => Carbon::parse($fiscalStart)->format('d M Y'),
            'assets_rows' => $this->sectionRows([Account::TYPE_ASSET], $assetBalances),
            'contra_assets_rows' => $this->sectionRows([Account::TYPE_CONTRA_ASSET], $contraAssetBalances),
            'liabilities_rows' => $this->sectionRows([Account::TYPE_LIABILITY], $liabilityBalances),
            'contra_liabilities_rows' => $this->sectionRows([Account::TYPE_CONTRA_LIABILITY], $contraLiabilityBalances),
            'equity_rows' => $this->sectionRows([Account::TYPE_EQUITY], $equityBalances),
            'total_assets_gross' => $totalAssetsGross,
            'total_contra_assets' => $totalContraAssets,
            'total_assets' => $totalAssets,
            'total_liabilities_gross' => $totalLiabilitiesGross,
            'total_contra_liabilities' => $totalContraLiabilities,
            'total_liabilities' => $totalLiabilities,
            'total_equity_booked' => $totalEquityBooked,
            'retained_earnings' => $retainedEarnings,
            'total_equity' => $totalEquity,
            'total_le' => $totalLE,
            'delta' => $delta,
            'balanced' => abs($delta) < 0.01,
        ];
    }

    // ---------------------------------------------------------------------
    // Cash Flow (indirect method)
    // ---------------------------------------------------------------------
    public function cashFlow(string $from, string $to): array
    {
        $netIncome = $this->netIncome($from, $to);

        // Follow the accountant's posting map: each built-in code also covers
        // any account the matching role has been remapped to (and vice-versa,
        // so history under the old code is still counted).
        $coa = app(ChartOfAccounts::class);

        $deltaAP = $this->deltaForCodes($coa->effectiveCodes(['2110']), $from, $to);
        $deltaDoctorPayable = $this->deltaForCodes($coa->effectiveCodes(['2130']), $from, $to);
        $deltaAR = $this->deltaForCodes($coa->effectiveCodes(['1140']), $from, $to);
        $deltaInventory = $this->deltaForCodes($coa->effectiveCodes(['1150']), $from, $to);

        $cashFromOps = $netIncome + $deltaAP + $deltaDoctorPayable - $deltaAR - $deltaInventory;

        $deltaFixedAssets = $this->deltaForCodes(['1210', '1220', '1230', '1240'], $from, $to);
        $cashFromInvesting = -$deltaFixedAssets;

        $deltaOwnerCapital = $this->deltaForCodes(['3100', '3110'], $from, $to);
        $cashFromFinancing = $deltaOwnerCapital;

        $netChange = $cashFromOps + $cashFromInvesting + $cashFromFinancing;

        // Cash & cash-equivalents = petty cash + bank + card settlement clearing.
        $cashCodes = $coa->effectiveCodes(['1110', '1120', '1130']);
        $cashStart = $this->balanceAtByCodes($cashCodes, Carbon::parse($from)->subDay()->toDateString());
        $cashEnd = $this->balanceAtByCodes($cashCodes, $to);
        $cashEndComputed = $cashStart + $netChange;
        $verificationDelta = $cashEnd - $cashEndComputed;

        return [
            'net_income' => $netIncome,
            'delta_ap' => $deltaAP,
            'delta_doctor_payable' => $deltaDoctorPayable,
            'delta_ar' => $deltaAR,
            'delta_inventory' => $deltaInventory,
            'cash_from_ops' => $cashFromOps,
            'delta_fixed_assets' => $deltaFixedAssets,
            'cash_from_investing' => $cashFromInvesting,
            'delta_owner_capital' => $deltaOwnerCapital,
            'cash_from_financing' => $cashFromFinancing,
            'net_change' => $netChange,
            'cash_start' => $cashStart,
            'cash_end' => $cashEnd,
            'cash_end_computed' => $cashEndComputed,
            'verification_delta' => $verificationDelta,
            'reconciles' => abs($verificationDelta) < 0.01,
        ];
    }

    // ---------------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------------

    /** Per-account net balance (natural direction) for a period. [id => float] */
    protected function balancesForTypes(array $types, string $from, string $to): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('e.entry_date', [$from, $to])
            ->whereIn('a.type', $types)
            ->groupBy('a.id', 'a.type')
            ->select('a.id', 'a.type', DB::raw('SUM(l.debit) as debit_sum'), DB::raw('SUM(l.credit) as credit_sum'))
            ->get();

        return $this->signed($rows);
    }

    /** Per-account net balance (natural direction) cumulative through a date. [id => float] */
    protected function balancesAt(array $types, string $asOf): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereDate('e.entry_date', '<=', $asOf)
            ->whereIn('a.type', $types)
            ->groupBy('a.id', 'a.type')
            ->select('a.id', 'a.type', DB::raw('SUM(l.debit) as debit_sum'), DB::raw('SUM(l.credit) as credit_sum'))
            ->get();

        return $this->signed($rows);
    }

    /** Turn a debit/credit/type result set into [id => natural-direction net]. */
    protected function signed($rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $debit = (float) $r->debit_sum;
            $credit = (float) $r->credit_sum;
            $isDebitNormal = in_array($r->type, Account::DEBIT_NORMAL_TYPES, true);
            $out[$r->id] = $isDebitNormal ? $debit - $credit : $credit - $debit;
        }

        return $out;
    }

    /** Section with hierarchy rows + total (used by P&L). */
    protected function section(array $types, array $balances): array
    {
        return ['rows' => $this->sectionRows($types, $balances), 'total' => array_sum($balances)];
    }

    /** Depth-aware, rolled-up row list for a set of account types. */
    protected function sectionRows(array $types, array $balances): array
    {
        $accounts = Account::query()->whereIn('type', $types)->orderBy('code')->get();
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

    /** Net income (P&L bottom line) for a period. */
    protected function netIncome(string $from, string $to): float
    {
        $sum = function (array $types) use ($from, $to): array {
            $r = DB::table('journal_entry_lines as l')
                ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
                ->where('e.status', JournalEntry::STATUS_POSTED)
                ->whereBetween('e.entry_date', [$from, $to])
                ->whereIn('a.type', $types)
                ->select(DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
                ->first();

            return [(float) ($r->d ?? 0), (float) ($r->c ?? 0)];
        };

        [$rD, $rC] = $sum([Account::TYPE_REVENUE]);
        [$crD, $crC] = $sum([Account::TYPE_CONTRA_REVENUE]);
        [$coD, $coC] = $sum([Account::TYPE_COGS]);
        [$eD, $eC] = $sum([Account::TYPE_EXPENSE]);

        $revenue = $rC - $rD;
        $contraRevenue = $crD - $crC;
        $cogs = $coD - $coC;
        $expense = $eD - $eC;

        return $revenue - $contraRevenue - $cogs - $expense;
    }

    /** Net balance (natural direction) for account ids, as of a date. */
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
            ->select('a.id', 'a.type', DB::raw('SUM(l.debit) as debit_sum'), DB::raw('SUM(l.credit) as credit_sum'))
            ->get();

        return array_sum($this->signed($rows));
    }

    /** Net balance for accounts matched by code prefixes, as of a date. */
    protected function balanceAtByCodes(array $codePrefixes, string $asOf): float
    {
        return $this->balanceAtForIds($this->idsForCodes($codePrefixes), $asOf);
    }

    /** Δ balance over [from..to] = balance_at(to) − balance_at(from−1). */
    protected function deltaForCodes(array $codePrefixes, string $from, string $to): float
    {
        $ids = $this->idsForCodes($codePrefixes);
        $end = $this->balanceAtForIds($ids, $to);
        $start = $this->balanceAtForIds($ids, Carbon::parse($from)->subDay()->toDateString());

        return $end - $start;
    }

    /** @return array<int> */
    protected function idsForCodes(array $codePrefixes): array
    {
        return Account::query()
            ->where(function ($w) use ($codePrefixes) {
                foreach ($codePrefixes as $p) {
                    $w->orWhere('code', 'like', $p.'%');
                }
            })
            ->pluck('id')->all();
    }
}
