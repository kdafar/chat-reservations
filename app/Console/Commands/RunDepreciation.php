<?php

namespace App\Console\Commands;

use App\Services\Accounting\DepreciationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Monthly straight-line depreciation run. Idempotent per (asset, month) via the
 * fixed_asset_depreciations ledger, so re-running a month is safe. Scheduled
 * near month-end; pass --month to (re)run a specific month.
 */
class RunDepreciation extends Command
{
    protected $signature = 'accounting:run-depreciation
                            {--month= : Month to depreciate, YYYY-MM (default: current month)}';

    protected $description = 'Post one month of straight-line depreciation for every active fixed asset. Idempotent.';

    public function handle(DepreciationService $svc): int
    {
        $month = $this->option('month')
            ? Carbon::parse($this->option('month').'-01')
            : now(config('app.timezone'))->startOfMonth();

        $r = $svc->runForMonth($month);

        $this->info("Depreciation {$r['period']}: posted {$r['posted']}/{$r['assets']} assets, total ".number_format($r['total'], 3).' KWD');

        return self::SUCCESS;
    }
}
