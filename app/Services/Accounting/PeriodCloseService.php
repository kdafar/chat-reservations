<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Facades\DB;

/**
 * Period Close workflow.
 *
 * Closing zeros every P&L account (revenue / cogs / expense / contra_revenue)
 * by transferring its net balance into Retained Earnings (3020), then locks
 * the period so no further entries can be posted in that date range.
 *
 * Reopen reverses the closing journal entry and flips the period back to open.
 */
class PeriodCloseService
{
    public function __construct(protected ChartOfAccounts $coa) {}

    /**
     * Close a period: refuse if drafts exist, then generate the closing
     * journal entry that zeros all P&L accounts into Retained Earnings.
     *
     * Returns the closing JournalEntry or throws if the period can't be closed.
     */
    public function close(AccountingPeriod $period, ?int $userId = null): JournalEntry
    {
        if ($period->isClosed()) {
            throw new \RuntimeException("Period {$period->code} is already closed.");
        }

        // Refuse if drafts exist in the period
        $draftCount = JournalEntry::query()
            ->where('status', JournalEntry::STATUS_DRAFT)
            ->whereBetween('entry_date', [$period->start_date, $period->end_date])
            ->count();
        if ($draftCount > 0) {
            throw new \RuntimeException(
                "Cannot close period {$period->code}: {$draftCount} draft entries exist in this date range. "
                .'Post or delete them first.'
            );
        }

        return DB::transaction(function () use ($period, $userId) {
            $endDate = $period->end_date->toDateString();

            // Find all accounts with non-zero balance of type revenue/cogs/expense/contra_revenue
            $closeableTypes = [
                Account::TYPE_REVENUE,
                Account::TYPE_COGS,
                Account::TYPE_EXPENSE,
                Account::TYPE_CONTRA_REVENUE,
            ];

            $accounts = Account::whereIn('type', $closeableTypes)->where('is_active', true)->get();

            $lines = [];
            $netToRetainedEarnings = 0.0;

            foreach ($accounts as $account) {
                $balance = $account->balanceAt($endDate); // signed by natural direction
                if (abs($balance) < 0.001) {
                    continue;
                }

                // To zero a debit-normal account: post a CREDIT line of $balance amount.
                // To zero a credit-normal account: post a DEBIT line of $balance amount.
                if ($account->isDebitNormal()) {
                    // Has a positive natural balance on debit side; credit it to zero
                    $lines[] = [
                        'account_id' => $account->id,
                        'debit' => 0,
                        'credit' => $balance,
                        'description' => "Close {$account->code} into Retained Earnings",
                    ];
                    // This means $balance of EXPENSE goes against RE (decrease equity)
                    $netToRetainedEarnings -= $balance;
                } else {
                    // Credit-normal (revenue etc.): debit it to zero
                    $lines[] = [
                        'account_id' => $account->id,
                        'debit' => $balance,
                        'credit' => 0,
                        'description' => "Close {$account->code} into Retained Earnings",
                    ];
                    // This means $balance of REVENUE goes to RE (increase equity)
                    $netToRetainedEarnings += $balance;
                }
            }

            if (empty($lines)) {
                throw new \RuntimeException("Period {$period->code} has no P&L activity to close.");
            }

            // Counter-line into Retained Earnings (3400).
            $retained = $this->coa->resolve('3400');
            if (! $retained) {
                throw new \RuntimeException('Retained Earnings account (3400) missing — seed CoA first.');
            }

            // RE is credit-normal. If netToRetainedEarnings > 0 we made profit (credit RE);
            // if negative we made a loss (debit RE).
            // Skip entirely on exact zero — JournalEntryLine rejects 0/0 lines
            // (audit follow-up #4), and a balanced break-even period needs no
            // RE counter-entry anyway.
            if (abs($netToRetainedEarnings) >= 0.001 && $netToRetainedEarnings > 0) {
                $lines[] = [
                    'account_id' => $retained->id,
                    'debit' => 0,
                    'credit' => $netToRetainedEarnings,
                    'description' => "Net profit for period {$period->code} → Retained Earnings",
                ];
            } elseif ($netToRetainedEarnings < -0.001) {
                $lines[] = [
                    'account_id' => $retained->id,
                    'debit' => abs($netToRetainedEarnings),
                    'credit' => 0,
                    'description' => "Net loss for period {$period->code} → Retained Earnings",
                ];
            }
            // else: exact break-even, no RE counter-line needed (closing
            // lines for revenue/expense already net to zero among themselves).

            // Create the closing JE in DRAFT first, source-tagged to the period.
            $entry = JournalEntry::create([
                'entry_date' => $endDate,
                'narration' => "Closing journal entry for period {$period->code}",
                'status' => JournalEntry::STATUS_DRAFT,
                'source_type' => AccountingPeriod::class,
                'source_id' => $period->id,
                'currency' => 'KWD',
                'meta' => ['close_type' => 'period_close', 'period_code' => $period->code],
            ]);

            foreach ($lines as $ln) {
                JournalEntryLine::create(array_merge($ln, [
                    'journal_entry_id' => $entry->id,
                    'currency' => 'KWD',
                ]));
            }

            // post() validates balance and sets period FK
            $entry->post($userId);

            // Mark the period closed AFTER the JE is safely posted.
            $period->forceFill([
                'status' => AccountingPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $userId,
            ])->save();

            return $entry;
        });
    }

    /**
     * Reopen a closed period: reverse the closing JE, flip status back to open.
     */
    public function reopen(AccountingPeriod $period, ?int $userId = null): void
    {
        if (! $period->isClosed()) {
            throw new \RuntimeException("Period {$period->code} is not closed.");
        }

        DB::transaction(function () use ($period, $userId) {
            // Find the closing JE (source = AccountingPeriod, status = posted)
            $closingEntry = JournalEntry::query()
                ->where('source_type', AccountingPeriod::class)
                ->where('source_id', $period->id)
                ->where('status', JournalEntry::STATUS_POSTED)
                ->latest('id')
                ->first();

            // We must open the period BEFORE reversing, because reverse() posts a
            // new entry dated today and that period might also be closed but ours
            // is the relevant one. Set status=open up front, then reverse.
            $period->forceFill([
                'status' => AccountingPeriod::STATUS_OPEN,
                'closed_at' => null,
                'closed_by_user_id' => null,
            ])->save();

            if ($closingEntry) {
                $closingEntry->reverse($userId, "Period {$period->code} reopened");
            }
        });
    }
}
