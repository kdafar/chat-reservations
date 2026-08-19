<?php

namespace Database\Seeders\Demo;

use App\Models\Visit;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the visit revenue accruals from the visits themselves.
 *
 * Re-costing a visit makes the accounting service reverse the old accrual and
 * post a replacement. Both happen inside one transaction, so when the
 * replacement failed — see the JournalEntry::generateCode() sequence bug — the
 * whole thing rolled back and the visit kept its stale pre-recompute entry.
 * After a bulk re-rate that left GL revenue ~180k KWD adrift from the visits.
 *
 * Dropping every visit-sourced entry and posting once from the current visit
 * totals is the only way to guarantee the ledger matches the source documents.
 * Safe to re-run: it is a full rebuild, not an increment.
 */
class DemoGlRebuildSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $this->clearVisitEntries();
        $this->repost();
        $this->reconcile();
    }

    protected function clearVisitEntries(): void
    {
        $sourced = DB::table('journal_entries')->where('source_type', Visit::class)->pluck('id');
        $reversals = DB::table('journal_entries')->whereIn('reversal_of_id', $sourced)->pluck('id');
        $all = $sourced->merge($reversals)->unique()->values()->all();

        foreach (array_chunk($all, 1000) as $chunk) {
            DB::table('journal_entry_lines')->whereIn('journal_entry_id', $chunk)->delete();
            DB::table('journal_entries')->whereIn('id', $chunk)->delete();
        }

        $this->command?->info('DemoGlRebuildSeeder: cleared '.count($all).' visit revenue entries.');
    }

    protected function repost(): void
    {
        $accounting = app(AccountingService::class);
        $posted = 0;
        $failed = 0;

        Visit::query()->withoutGlobalScopes()
            ->where('status', Visit::STATUS_COMPLETED)
            ->orderBy('id')
            ->chunkById(400, function ($visits) use ($accounting, &$posted, &$failed) {
                foreach ($visits as $visit) {
                    // Recognise the revenue on the day the visit closed.
                    Carbon::setTestNow(Carbon::parse($visit->completed_at ?? $visit->computed_at));
                    $entry = $accounting->recordVisitRevenueAccrual($visit);
                    Carbon::setTestNow();

                    $entry ? $posted++ : $failed++;
                    if (($posted + $failed) % 1000 === 0) {
                        $this->command?->info('  '.($posted + $failed).' visits re-posted');
                    }
                }
            });

        Carbon::setTestNow();
        $this->command?->info("DemoGlRebuildSeeder: posted {$posted} accruals ({$failed} produced none).");
    }

    /** Prove the ledger agrees with the source documents before we call it done. */
    protected function reconcile(): void
    {
        $visits = (float) DB::table('visits')->where('status', 'completed')
            ->selectRaw('SUM(fees_total + packages_price_total + items_price_total - discount_total) v')->value('v');

        $gl = (float) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereIn('chart_of_accounts.type', ['revenue', 'contra_revenue'])
            ->sum(DB::raw('credit - debit'));

        $gap = round($visits - $gl, 3);
        $this->command?->info(sprintf(
            'DemoGlRebuildSeeder: visits %s KWD vs GL %s KWD — %s',
            number_format($visits, 3), number_format($gl, 3),
            abs($gap) < 1 ? 'RECONCILED' : 'GAP OF '.number_format($gap, 3),
        ));
    }
}
