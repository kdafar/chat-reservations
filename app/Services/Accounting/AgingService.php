<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GL-based open-item aging. Walks the receivable / payable sub-ledger lines
 * (posted only) per counterparty, settles credits against the oldest open
 * debits (FIFO), and buckets whatever is still open by age as of a date.
 *
 * Receivables are aged by patient (the patient_id line dimension), with insurer
 * coverage and other unattributed balances grouped separately. Payables are
 * aged by vendor, resolved from each entry's source document.
 */
class AgingService
{
    /** Standard 30-day aging buckets. */
    public const BUCKETS = ['current', 'd31_60', 'd61_90', 'd90_plus'];

    public function accountsReceivable(string $asOf, ?int $branchId = null): array
    {
        $accountIds = $this->receivableAccountIds();

        // Dedicated insurer AR accounts → one aging row per insurer (keyed by
        // account, since a claim's reclass debit + the insurer payment credit
        // both land there). Patient self-pay lives on the shared 1140 control
        // and is aged per patient via the patient_id dimension.
        $insurerByAccount = \App\Models\Insurance\Insurer::query()
            ->whereNotNull('ar_account_id')
            ->get(['id', 'name', 'ar_account_id'])
            ->keyBy('ar_account_id');

        $lines = $this->lines($accountIds, $asOf, $branchId)
            ->addSelect('l.account_id', 'l.patient_id', 'p.name as patient_name')
            ->leftJoin('patients as p', 'p.id', '=', 'l.patient_id')
            ->get();

        $groups = [];
        foreach ($lines as $l) {
            if ($insurer = $insurerByAccount->get($l->account_id)) {
                $key = 'insurer:'.$l->account_id;
                $label = ($insurer->name ?: 'Insurer').' (insurer)';
            } elseif ($l->patient_id) {
                $key = 'patient:'.$l->patient_id;
                $label = $l->patient_name ?: 'Patient #'.$l->patient_id;
            } else {
                $key = 'unattributed';
                $label = 'Unattributed receivables';
            }
            $groups[$key]['label'] ??= $label;
            $groups[$key]['lines'][] = $l;
        }

        return $this->bucketGroups($groups, $asOf);
    }

    public function accountsPayable(string $asOf, ?int $branchId = null): array
    {
        $accountIds = $this->payableAccountIds();

        $lines = $this->lines($accountIds, $asOf, $branchId)
            ->addSelect('e.source_type', 'e.source_id')
            ->get();

        // Resolve a vendor label per (source_type, source_id) once.
        $vendorCache = [];
        $groups = [];
        foreach ($lines as $l) {
            [$key, $label] = $this->vendorFor($l->source_type, $l->source_id, $vendorCache);
            $groups[$key]['label'] ??= $label;
            $groups[$key]['lines'][] = $l;
        }

        return $this->bucketGroups($groups, $asOf, payable: true);
    }

    // -----------------------------------------------------------------

    /** Posted sub-ledger lines up to $asOf for a set of accounts. */
    protected function lines(array $accountIds, string $asOf, ?int $branchId)
    {
        if (empty($accountIds)) {
            // Force an empty result without a malformed IN ().
            $accountIds = [0];
        }

        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)
            ->whereIn('l.account_id', $accountIds)
            ->whereDate('e.entry_date', '<=', $asOf)
            ->when($branchId, fn ($q) => $q->where('l.branch_id', $branchId))
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')
            ->select('l.debit', 'l.credit', 'e.entry_date');
    }

    /**
     * FIFO-settle each group's lines and bucket the still-open items by age.
     * For receivables a debit opens an item and a credit settles it; for
     * payables it is the mirror (credit opens, debit settles).
     */
    protected function bucketGroups(array $groups, string $asOf, bool $payable = false): array
    {
        $asOfDate = Carbon::parse($asOf);
        $rows = [];
        $totals = array_fill_keys(self::BUCKETS, 0.0) + ['total' => 0.0];

        foreach ($groups as $g) {
            $open = []; // queue of [entry_date, remaining amount]
            foreach ($g['lines'] as $l) {
                $opening = $payable ? (float) $l->credit : (float) $l->debit;
                $settling = $payable ? (float) $l->debit : (float) $l->credit;

                if ($opening > 0) {
                    $open[] = [$l->entry_date, $opening];
                }
                $remainingCredit = $settling;
                while ($remainingCredit > 0.0005 && ! empty($open)) {
                    $applied = min($remainingCredit, $open[0][1]);
                    $open[0][1] = round($open[0][1] - $applied, 3);
                    $remainingCredit = round($remainingCredit - $applied, 3);
                    if ($open[0][1] <= 0.0005) {
                        array_shift($open);
                    }
                }
            }

            $row = array_fill_keys(self::BUCKETS, 0.0) + ['label' => $g['label'], 'total' => 0.0];
            foreach ($open as [$date, $amt]) {
                $amt = round($amt, 3);
                if ($amt <= 0) {
                    continue;
                }
                $age = Carbon::parse($date)->diffInDays($asOfDate, false);
                $bucket = match (true) {
                    $age <= 30 => 'current',
                    $age <= 60 => 'd31_60',
                    $age <= 90 => 'd61_90',
                    default => 'd90_plus',
                };
                $row[$bucket] = round($row[$bucket] + $amt, 3);
                $row['total'] = round($row['total'] + $amt, 3);
            }

            if (abs($row['total']) < 0.0005) {
                continue; // fully settled — omit
            }
            foreach (self::BUCKETS as $b) {
                $totals[$b] = round($totals[$b] + $row[$b], 3);
            }
            $totals['total'] = round($totals['total'] + $row['total'], 3);
            $rows[] = $row;
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return ['rows' => $rows, 'totals' => $totals, 'as_of' => $asOf];
    }

    /** @return array<int> AR control accounts (1140 + any insurer AR accounts). */
    protected function receivableAccountIds(): array
    {
        return Account::query()
            ->where('type', Account::TYPE_ASSET)
            ->where(function ($q) {
                $q->where('code', 'like', '1140%')
                    ->orWhereIn('id', \App\Models\Insurance\Insurer::query()->whereNotNull('ar_account_id')->pluck('ar_account_id')->all() ?: [0]);
            })
            ->pluck('id')->all();
    }

    /** @return array<int> AP control accounts (2110 + vendor custom payable accounts). */
    protected function payableAccountIds(): array
    {
        $vendorAccts = DB::table('vendors')->whereNotNull('default_payable_account_id')->pluck('default_payable_account_id')->all();

        return Account::query()
            ->where('type', Account::TYPE_LIABILITY)
            ->where(function ($q) use ($vendorAccts) {
                $q->where('code', 'like', '2110%');
                if (! empty($vendorAccts)) {
                    $q->orWhereIn('id', $vendorAccts);
                }
            })
            ->pluck('id')->all();
    }

    /** Resolve [key, label] vendor for a payable entry's polymorphic source. */
    protected function vendorFor(?string $sourceType, $sourceId, array &$cache): array
    {
        $ck = $sourceType.':'.$sourceId;
        if (isset($cache[$ck])) {
            return $cache[$ck];
        }

        $vendor = null;
        try {
            if ($sourceType && $sourceId && class_exists($sourceType)) {
                $model = $sourceType::query()->find($sourceId);
                $vendor = match (true) {
                    $model instanceof \App\Models\Accounting\Expense => $model->vendor,
                    $model instanceof \App\Models\Purchasing\PurchasePayment => $model->vendor,
                    $model instanceof \App\Models\Purchasing\PurchaseReceipt => $model->purchaseOrder?->vendor,
                    default => null,
                };
            }
        } catch (\Throwable $e) {
            $vendor = null;
        }

        $out = $vendor
            ? ['vendor:'.$vendor->id, $vendor->name]
            : ['unattributed', 'Unattributed payables'];

        return $cache[$ck] = $out;
    }
}
