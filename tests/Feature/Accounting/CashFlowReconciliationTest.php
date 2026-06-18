<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FixedAsset;
use App\Models\ClinicItem;
use App\Models\ClinicStockMovement;
use App\Models\VisitPayment;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ChartOfAccounts;
use App\Services\Accounting\DepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class CashFlowReconciliationTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        app(ChartOfAccounts::class)->refresh();
    }

    public function test_cash_flow_reconciles_across_accrual_depreciation_and_working_capital(): void
    {
        $svc = app(AccountingService::class);
        $from = '2026-03-01';
        $to = '2026-03-31';
        $when = Carbon::parse('2026-03-10');

        // Revenue accrual (Dr AR 100 / Cr revenue 100).
        $visit = $this->makeVisit(['fees_total' => 100.000, 'completed_at' => $when]);
        $svc->recordVisitRevenueAccrual($visit->refresh());

        // Partial cash payment (Dr cash 60 / Cr AR 60).
        $pay = VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 60.000, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation', 'paid_at' => $when,
        ]);
        $svc->recordVisitPayment($pay);

        // Depreciation (Dr 6610 100 / Cr 1215 100) — non-cash, must add back.
        FixedAsset::create([
            'code' => 'FA-CF', 'name' => 'Device', 'category' => 'medical_equipment',
            'asset_account_id' => $this->account('1210')->id,
            'accumulated_depreciation_account_id' => $this->account('1215')->id,
            'depreciation_expense_account_id' => $this->account('6610')->id,
            'cost' => 1200.000, 'salvage_value' => 0, 'useful_life_months' => 12,
            'in_service_date' => Carbon::parse('2026-01-01'), 'status' => FixedAsset::STATUS_ACTIVE,
        ]);
        app(DepreciationService::class)->runForMonth($when);

        $cf = app(AccountingReportService::class)->cashFlow($from, $to);

        // Net income 0 (revenue 100 − depreciation 100); add back depreciation
        // 100; less the rise in receivables (40 still owed) ⇒ operating cash 60,
        // which equals the actual cash received. The statement must tie out with
        // nothing unclassified — proving depreciation & accruals are captured.
        $this->assertEqualsWithDelta(0.0, $cf['net_income'], 0.001);
        $this->assertEqualsWithDelta(100.0, $cf['depreciation_addback'], 0.001);
        $this->assertEqualsWithDelta(60.0, $cf['net_change'], 0.001);
        $this->assertEqualsWithDelta(0.0, $cf['unclassified'], 0.001, 'Cash flow must fully reconcile');
        $this->assertTrue($cf['reconciles']);
    }
}
