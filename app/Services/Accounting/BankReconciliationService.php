<?php

namespace App\Services\Accounting;

use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankStatementLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Operational glue for the bank reconciliation workflow:
 *
 *   - createForAccountAndPeriod : open a new reconciliation
 *   - recomputeBalances         : refresh book-side numbers from the GL
 *   - autoMatch                 : best-effort pairing by amount + date
 *   - importCsv                 : ingest statement rows from a CSV file
 */
class BankReconciliationService
{
    /**
     * Spin up a new in-progress reconciliation and compute book balances.
     */
    public function createForAccountAndPeriod(
        int $accountId,
        string $start,
        string $end,
        float $openingBalance,
        float $closingBalance,
    ): BankReconciliation {
        return DB::transaction(function () use ($accountId, $start, $end, $openingBalance, $closingBalance) {
            $rec = BankReconciliation::create([
                'account_id' => $accountId,
                'period_start' => $start,
                'period_end' => $end,
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'status' => BankReconciliation::STATUS_IN_PROGRESS,
            ]);

            $rec->recomputeBookBalances();
            $rec->save();

            return $rec->refresh();
        });
    }

    /** Recompute and persist book opening/closing balances. */
    public function recomputeBalances(BankReconciliation $rec): void
    {
        $rec->recomputeBookBalances();
        $rec->save();
    }

    /**
     * Best-effort auto-matcher: for each unmatched statement line, find an
     * unmatched JE line on the same account where amounts agree (in either
     * Dr↔Cr direction) AND dates are within ±2 days. Pairs the first match.
     *
     * Returns count of new matches created.
     */
    public function autoMatch(BankReconciliation $rec): int
    {
        $matched = 0;
        $tolerance = 0.005;
        $dateWindow = 2; // days

        $rec->load(['statementLines', 'account']);

        // Pre-fetch all unmatched book lines once.
        $bookLines = $rec->unreconciled_book_lines;

        // Take only unmatched bank lines, ordered by date.
        $bankLines = $rec->statementLines()
            ->whereNull('matched_journal_entry_line_id')
            ->orderBy('statement_date')
            ->orderBy('id')
            ->get();

        // Track which book line IDs we've used in THIS pass so we don't pair
        // two bank lines to the same JE line.
        $usedBookIds = [];

        foreach ($bankLines as $bankLine) {
            $bankAmount = $bankLine->statementAmount();
            if ($bankAmount <= 0) {
                continue;
            }

            $bankDate = $bankLine->statement_date instanceof Carbon
                ? $bankLine->statement_date->copy()
                : Carbon::parse($bankLine->statement_date);

            foreach ($bookLines as $bookLine) {
                if (in_array($bookLine->id, $usedBookIds, true)) {
                    continue;
                }

                $jeAmount = (float) $bookLine->debit > 0
                    ? (float) $bookLine->debit
                    : (float) $bookLine->credit;

                if (abs($bankAmount - $jeAmount) > $tolerance) {
                    continue;
                }

                $jeDate = $bookLine->entry?->entry_date;
                if (! $jeDate) {
                    continue;
                }
                $jeDate = $jeDate instanceof Carbon ? $jeDate->copy() : Carbon::parse($jeDate);

                if (abs($bankDate->diffInDays($jeDate, false)) > $dateWindow) {
                    continue;
                }

                // Try to pair. If the amount-direction sanity check fails
                // inside match() we just log and move on.
                try {
                    $bankLine->match((int) $bookLine->id);
                    $usedBookIds[] = $bookLine->id;
                    $matched++;
                    break; // next bank line
                } catch (\Throwable $e) {
                    Log::info('[BankReconciliationService::autoMatch] candidate rejected', [
                        'bank_line_id' => $bankLine->id,
                        'book_line_id' => $bookLine->id,
                        'reason' => $e->getMessage(),
                    ]);

                    continue;
                }
            }
        }

        return $matched;
    }

    /**
     * Import a simple CSV: header row of date,description,debit,credit,reference
     * (any case, in any column order). Rows with both debit==0 and credit==0
     * are skipped silently. Returns rows inserted.
     */
    public function importCsv(BankReconciliation $rec, string $csvPath): int
    {
        if (! is_file($csvPath) || ! is_readable($csvPath)) {
            throw new \RuntimeException("CSV file not readable: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV: {$csvPath}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === null) {
                return 0;
            }

            // Normalize header keys: lowercase + trim.
            $header = array_map(
                fn ($h) => strtolower(trim((string) $h)),
                $header,
            );

            $idx = [
                'date' => array_search('date', $header, true),
                'description' => array_search('description', $header, true),
                'debit' => array_search('debit', $header, true),
                'credit' => array_search('credit', $header, true),
                'reference' => array_search('reference', $header, true),
            ];

            if ($idx['date'] === false) {
                throw new \RuntimeException('CSV missing required "date" column.');
            }
            if ($idx['debit'] === false && $idx['credit'] === false) {
                throw new \RuntimeException('CSV must have at least one of "debit" or "credit" columns.');
            }

            $inserted = 0;

            DB::transaction(function () use ($handle, $idx, $rec, &$inserted) {
                while (($row = fgetcsv($handle)) !== false) {
                    // Skip empty rows.
                    if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                        continue;
                    }

                    $get = fn (string $key) => $idx[$key] !== false && isset($row[$idx[$key]])
                        ? trim((string) $row[$idx[$key]])
                        : null;

                    $dateStr = $get('date');
                    if (! $dateStr) {
                        continue;
                    }

                    try {
                        $date = Carbon::parse($dateStr)->toDateString();
                    } catch (\Throwable $e) {
                        Log::warning('[BankReconciliationService::importCsv] bad date', [
                            'value' => $dateStr,
                        ]);

                        continue;
                    }

                    $debit = (float) ($get('debit') ?? 0);
                    $credit = (float) ($get('credit') ?? 0);

                    if ($debit <= 0 && $credit <= 0) {
                        continue; // nothing to record
                    }

                    BankStatementLine::create([
                        'bank_reconciliation_id' => $rec->id,
                        'statement_date' => $date,
                        'description' => $get('description'),
                        'reference' => $get('reference'),
                        'debit' => max(0, $debit),
                        'credit' => max(0, $credit),
                    ]);

                    $inserted++;
                }
            });

            return $inserted;
        } finally {
            fclose($handle);
        }
    }
}
