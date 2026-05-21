<?php

namespace Database\Seeders;

use App\Models\ClinicStockMovement;
use App\Models\DoctorCompensationLedger;
use App\Models\VisitPayment;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ChartOfAccounts;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Posts journal entries for every clinic event that pre-dates the
 * observer registration. Idempotent: re-running won't duplicate entries
 * (AccountingService::existingFor() handles dedup).
 *
 * Uses Carbon::setTestNow() per row so the posted_at timestamps match
 * the historical dates of the underlying events.
 */
class AccountingBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== Accounting Backfill ===');

        // Refresh the in-memory chart-of-accounts cache before posting.
        app(ChartOfAccounts::class)->refresh();
        $accounting = app(AccountingService::class);

        // ---------- 1. Visit Payments ----------
        $this->command->info('Backfilling visit payments...');
        $paymentCount = 0;
        VisitPayment::query()->orderBy('id')->chunk(200, function ($chunk) use ($accounting, &$paymentCount) {
            foreach ($chunk as $payment) {
                Carbon::setTestNow($payment->paid_at ?? $payment->created_at ?? now());
                $accounting->recordVisitPayment($payment);
                $paymentCount++;
            }
        });
        Carbon::setTestNow();
        $this->command->info("  processed {$paymentCount} payments");

        // ---------- 2. Stock Movements ----------
        $this->command->info('Backfilling stock movements...');
        $consumeCount = 0;
        $restockCount = 0;
        ClinicStockMovement::query()->orderBy('id')->chunk(200, function ($chunk) use ($accounting, &$consumeCount, &$restockCount) {
            foreach ($chunk as $movement) {
                Carbon::setTestNow($movement->created_at ?? now());
                if ($movement->type === 'consume') {
                    $accounting->recordStockConsume($movement);
                    $consumeCount++;
                } elseif ($movement->type === 'restock') {
                    $accounting->recordStockRestock($movement);
                    $restockCount++;
                }
            }
        });
        Carbon::setTestNow();
        $this->command->info("  processed {$consumeCount} consume, {$restockCount} restock");

        // ---------- 3. Doctor Compensation Ledgers ----------
        $this->command->info('Backfilling doctor compensation...');
        $compCount = 0;
        DoctorCompensationLedger::query()->orderBy('id')->chunk(200, function ($chunk) use ($accounting, &$compCount) {
            foreach ($chunk as $ledger) {
                Carbon::setTestNow($ledger->created_at ?? now());
                $accounting->recordDoctorCompensation($ledger);
                $compCount++;
            }
        });
        Carbon::setTestNow();
        $this->command->info("  processed {$compCount} comp ledgers");

        // ---------- Summary ----------
        $jeCount = DB::table('journal_entries')->count();
        $lineCount = DB::table('journal_entry_lines')->count();
        $postedDebit = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->sum('debit');
        $postedCredit = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->sum('credit');

        $this->command->info('');
        $this->command->info("Total journal entries: {$jeCount}");
        $this->command->info("Total lines:           {$lineCount}");
        $this->command->info('Total debits:          '.number_format((float) $postedDebit, 3).' KWD');
        $this->command->info('Total credits:         '.number_format((float) $postedCredit, 3).' KWD');
        $this->command->info('Balance check:         '.(abs($postedDebit - $postedCredit) < 0.01 ? '✓ books balance' : '✗ MISMATCH'));
    }
}
