<?php

namespace Tests\Feature\Clinic;

use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class VisitModelTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
    }

    public function test_booted_hook_sets_service_started_at_on_in_progress_transition(): void
    {
        $visit = $this->makeVisit([
            'status' => 'awaiting_doctor',
            'completed_at' => null,
        ]);
        $visit->refresh();
        $this->assertNull($visit->service_started_at);

        $visit->update(['status' => 'in_progress']);

        $visit->refresh();
        $this->assertNotNull($visit->service_started_at, 'service_started_at should auto-set on in_progress');
    }

    public function test_booted_hook_sets_completed_at_on_completed_transition(): void
    {
        $visit = $this->makeVisit([
            'status' => 'in_progress',
            'completed_at' => null,
            'service_started_at' => now()->subMinutes(30),
        ]);

        $visit->update(['status' => 'completed']);

        $visit->refresh();
        $this->assertNotNull($visit->completed_at);
    }

    public function test_booted_hook_does_not_clobber_existing_timestamps(): void
    {
        $original = now()->subDays(3);
        $visit = $this->makeVisit([
            'status' => 'in_progress',
            'service_started_at' => $original,
            'completed_at' => null,
        ]);

        // Update unrelated field — the booted hook should NOT touch service_started_at
        $visit->update(['notes' => 'new note']);

        $visit->refresh();
        $this->assertEquals(
            $original->timestamp,
            $visit->service_started_at->timestamp,
            'service_started_at must be preserved when status is not changing'
        );
    }

    public function test_booted_hook_does_not_re_set_when_value_already_present(): void
    {
        $earlier = now()->subHour();
        $visit = $this->makeVisit([
            'status' => 'awaiting_doctor',
            'service_started_at' => $earlier,  // already set somehow
            'completed_at' => null,
        ]);

        $visit->update(['status' => 'in_progress']);

        $visit->refresh();
        // The hook only sets if currently empty — must preserve earlier value
        $this->assertEqualsWithDelta(
            $earlier->timestamp,
            $visit->service_started_at->timestamp,
            5,
            'service_started_at must NOT be re-stamped if it was already set'
        );
    }

    public function test_balance_due_accessor_includes_all_components(): void
    {
        $visit = $this->makeVisit([
            'fees_total' => 25.000,
            'packages_price_total' => 30.000,
            'items_price_total' => 10.000,
            'discount_total' => 5.000,
        ]);

        // total_paid is 0 (no payments), so balance = 25 + 30 + 10 - 5 = 60
        $this->assertEqualsWithDelta(60.0, $visit->balance_due, 0.001);
    }

    public function test_balance_due_subtracts_paid_amount(): void
    {
        $visit = $this->makeVisit([
            'fees_total' => 25.000,
            'packages_price_total' => 0,
            'items_price_total' => 0,
            'discount_total' => 0,
        ]);

        \App\Models\VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 15.000,
            'method' => 'cash',
            'status' => 'paid',
            'kind' => 'consultation',
            'paid_at' => now(),
        ]);

        $visit->refresh();
        // 25 billed − 15 paid = 10 due
        $this->assertEqualsWithDelta(10.0, $visit->balance_due, 0.001);
    }

    public function test_status_constants_include_all_lifecycle_states(): void
    {
        // Audit fix #9: awaiting_stock + awaiting_payment must exist as constants.
        $this->assertSame('awaiting_stock', Visit::STATUS_AWAITING_STOCK);
        $this->assertSame('awaiting_payment', Visit::STATUS_AWAITING_PAYMENT);
        $this->assertSame('in_progress', Visit::STATUS_IN_PROGRESS);
        $this->assertSame('completed', Visit::STATUS_COMPLETED);
        $this->assertSame('cancelled', Visit::STATUS_CANCELLED);
        $this->assertSame('no_show', Visit::STATUS_NO_SHOW);
    }
}
