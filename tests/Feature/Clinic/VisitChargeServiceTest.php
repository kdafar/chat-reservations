<?php

namespace Tests\Feature\Clinic;

use App\Models\VisitCharge;
use App\Services\Clinic\VisitChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class VisitChargeServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected VisitChargeService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(VisitChargeService::class);
    }

    public function test_add_charge_creates_row(): void
    {
        $visit = $this->makeVisit();

        $charge = $this->svc->addCharge($visit, 'Lab Test', 1, 15.000, 0);

        $this->assertInstanceOf(VisitCharge::class, $charge);
        $this->assertSame($visit->id, $charge->visit_id);
        $this->assertSame('Lab Test', $charge->label);
        $this->assertEqualsWithDelta(1.0, (float) $charge->qty, 0.001);
        $this->assertEqualsWithDelta(15.000, (float) $charge->unit_price_snapshot, 0.001);
        $this->assertEqualsWithDelta(15.000, (float) $charge->line_total, 0.001);
    }

    public function test_qty_multiplies_line_total(): void
    {
        $visit = $this->makeVisit();

        $charge = $this->svc->addCharge($visit, 'Procedure', 3, 10.000, 0);

        $this->assertEqualsWithDelta(30.000, (float) $charge->line_total, 0.001);
    }

    public function test_empty_label_throws(): void
    {
        $visit = $this->makeVisit();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('label is required');

        $this->svc->addCharge($visit, '', 1, 10.000, 0);
    }

    public function test_zero_qty_throws(): void
    {
        $visit = $this->makeVisit();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('qty must be > 0');

        $this->svc->addCharge($visit, 'Test', 0, 10.000, 0);
    }

    public function test_negative_unit_price_throws(): void
    {
        $visit = $this->makeVisit();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->svc->addCharge($visit, 'Test', 1, -5.000, 0);
    }

    public function test_zero_unit_price_is_allowed(): void
    {
        $visit = $this->makeVisit();

        // Free service / waived charge — allowed
        $charge = $this->svc->addCharge($visit, 'Goodwill Adjustment', 1, 0, 0);

        $this->assertEqualsWithDelta(0.0, (float) $charge->line_total, 0.001);
    }

    public function test_user_id_stamped_when_provided(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit();

        $charge = $this->svc->addCharge($visit, 'X-Ray', 1, 25.000, $f['user']->id);

        $this->assertSame($f['user']->id, $charge->added_by_user_id);
    }
}
