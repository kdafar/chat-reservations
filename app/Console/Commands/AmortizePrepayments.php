<?php

namespace App\Console\Commands;

use App\Services\Accounting\PrepaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Monthly prepaid-expense amortization run. Idempotent per (schedule, month)
 * via the prepaid_amortizations ledger.
 */
class AmortizePrepayments extends Command
{
    protected $signature = 'accounting:amortize-prepayments
                            {--month= : Month to amortize, YYYY-MM (default: current month)}';

    protected $description = 'Release one month of every active prepaid-expense schedule to the P&L. Idempotent.';

    public function handle(PrepaymentService $svc): int
    {
        $month = $this->option('month')
            ? Carbon::parse($this->option('month').'-01')
            : now(config('app.timezone'))->startOfMonth();

        $r = $svc->runForMonth($month);

        $this->info("Prepaid amortization {$r['period']}: posted {$r['posted']}/{$r['schedules']} schedules, total ".number_format($r['total'], 3).' KWD');

        return self::SUCCESS;
    }
}
