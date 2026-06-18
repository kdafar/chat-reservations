<?php

namespace Tests\Feature\Accounting;

use App\Models\VisitPayment;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\AgingService;
use App\Services\Accounting\ChartOfAccounts;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class AgingTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        app(ChartOfAccounts::class)->refresh();
    }

    public function test_ar_aging_buckets_open_balance_by_age_with_fifo_settlement(): void
    {
        $svc = app(AccountingService::class);
        $today = Carbon::now();

        // Old visit (100 days ago) billed 80, recent visit (10 days ago) billed 50.
        $old = $this->makeVisit(['fees_total' => 80.000, 'completed_at' => $today->copy()->subDays(100)]);
        $svc->recordVisitRevenueAccrual($old->refresh());

        $recent = $this->makeVisit(['fees_total' => 50.000, 'completed_at' => $today->copy()->subDays(10)]);
        $svc->recordVisitRevenueAccrual($recent->refresh());

        // A 30 payment settles the OLDEST receivable first (FIFO).
        $pay = VisitPayment::create([
            'visit_id' => $old->id, 'amount' => 30.000, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation', 'paid_at' => $today,
        ]);
        $svc->recordVisitPayment($pay);

        $aging = app(AgingService::class)->accountsReceivable($today->toDateString());

        // makeVisit reuses one patient → a single counterparty row.
        $this->assertCount(1, $aging['rows']);
        $row = $aging['rows'][0];

        // Old 80 − 30 = 50 still open at 90+; recent 50 in the current bucket.
        $this->assertEqualsWithDelta(50.0, $row['d90_plus'], 0.001, 'Aged 90+ after FIFO settlement');
        $this->assertEqualsWithDelta(50.0, $row['current'], 0.001, 'Recent charge is current');
        $this->assertEqualsWithDelta(100.0, $row['total'], 0.001);
        $this->assertEqualsWithDelta(100.0, $aging['totals']['total'], 0.001);
    }
}
