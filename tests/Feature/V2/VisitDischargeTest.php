<?php

namespace Tests\Feature\V2;

use App\Models\User;
use App\Models\VisitCharge;
use App\Models\VisitItem;
use App\Models\VisitPackage;
use App\Models\VisitPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Locks the discharge balance gate. The endpoint MUST compute the outstanding
 * balance net of per-line discounts — the same way transformVisit() does for
 * the can_discharge permission — so the UI and the endpoint never disagree.
 */
class VisitDischargeTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        config(['clinic.visit_financials_enabled' => true]);
    }

    private function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');
        $u = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@t.local',
            'password' => Hash::make('password'), 'status' => 'active',
        ]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_discharge_succeeds_when_net_balance_is_zero_despite_line_discount(): void
    {
        $visit = $this->makeVisit([
            'status' => 'awaiting_payment',
            'checked_in_at' => now()->subHour(),
            'completed_at' => null,
        ]);

        // Consultation: 25, no line discount.
        VisitCharge::create([
            'visit_id' => $visit->id, 'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee', 'qty' => 1,
            'unit_price_snapshot' => 25.000, 'line_total' => 25.000, 'discount_amount' => 0,
        ]);

        // Package gross 30, fully discounted to net 0 (a 100% promo). Gross
        // balance would be 55; net is 25 (consultation only).
        $pkg = $this->makeClinicPackage(['default_price' => 30.000]);
        VisitPackage::create([
            'visit_id' => $visit->id, 'clinic_package_id' => $pkg->id, 'branch_id' => $visit->branch_id,
            'qty' => 1, 'unit_price_snapshot' => 30.000, 'line_total' => 30.000, 'discount_amount' => 30.000,
        ]);

        // Pay the NET due (25). Under the old gross math this left 30 unpaid.
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 25.000, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation', 'paid_at' => now(),
        ]);

        $resp = $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/discharge");

        $resp->assertOk()->assertJson(['ok' => true]);

        $visit->refresh();
        $this->assertSame('completed', $visit->status);
        $this->assertNotNull($visit->completed_at);
    }

    public function test_discharge_rejected_when_a_real_net_balance_is_outstanding(): void
    {
        $visit = $this->makeVisit([
            'status' => 'awaiting_payment',
            'checked_in_at' => now()->subHour(),
            'completed_at' => null,
        ]);

        VisitCharge::create([
            'visit_id' => $visit->id, 'branch_id' => $visit->branch_id,
            'label' => 'Consultation Fee', 'qty' => 1,
            'unit_price_snapshot' => 25.000, 'line_total' => 25.000, 'discount_amount' => 0,
        ]);

        // A 10 KWD item with no discount → genuinely unpaid net balance.
        VisitItem::create([
            'visit_id' => $visit->id, 'clinic_item_id' => $this->makeClinicItem()->id,
            'branch_id' => $visit->branch_id, 'qty' => 1,
            'unit_cost_snapshot' => 2.000, 'unit_price_snapshot' => 10.000,
            'line_cost_total' => 2.000, 'line_price_total' => 10.000, 'discount_amount' => 0,
        ]);

        // Only consultation paid — 10 KWD still due.
        VisitPayment::create([
            'visit_id' => $visit->id, 'amount' => 25.000, 'method' => 'cash',
            'status' => 'paid', 'kind' => 'consultation', 'paid_at' => now(),
        ]);

        $resp = $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/discharge");

        $resp->assertStatus(422);
        $this->assertSame('awaiting_payment', $visit->fresh()->status);
    }
}
