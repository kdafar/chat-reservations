<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class AccountingPeriodTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    public function test_for_date_creates_a_month_bucket(): void
    {
        $p = AccountingPeriod::forDate('2026-05-15');

        $this->assertSame('2026-05', $p->code);
        $this->assertEquals('2026-05-01', $p->start_date->toDateString());
        $this->assertEquals('2026-05-31', $p->end_date->toDateString());
        $this->assertSame(AccountingPeriod::STATUS_OPEN, $p->status);
    }

    public function test_for_date_is_idempotent_for_same_month(): void
    {
        $a = AccountingPeriod::forDate('2026-05-15');
        $b = AccountingPeriod::forDate('2026-05-01');
        $c = AccountingPeriod::forDate('2026-05-31');

        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->id, $c->id);
        $this->assertSame(1, AccountingPeriod::count());
    }

    public function test_distinct_months_get_distinct_buckets(): void
    {
        AccountingPeriod::forDate('2026-04-30');
        AccountingPeriod::forDate('2026-05-01');

        $this->assertSame(2, AccountingPeriod::count());
        $this->assertNotNull(AccountingPeriod::where('code', '2026-04')->first());
        $this->assertNotNull(AccountingPeriod::where('code', '2026-05')->first());
    }

    public function test_is_closed_reflects_status(): void
    {
        $p = AccountingPeriod::forDate('2026-05-15');

        $this->assertFalse($p->isClosed());

        $p->forceFill(['status' => AccountingPeriod::STATUS_CLOSED, 'closed_at' => now()])->save();
        $this->assertTrue($p->isClosed());
    }
}
