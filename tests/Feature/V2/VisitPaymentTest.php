<?php

namespace Tests\Feature\V2;

use App\Models\User;
use Database\Seeders\ClinicPaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Phase 4 — payment method gating. Card/KNET/transfer require a reference id;
 * cash does not. Online payment links need a configured gateway.
 */
class VisitPaymentTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        // Global payment-method defaults (cash, knet/card/transfer/insurance=ref, link=online).
        $this->seed(ClinicPaymentMethodSeeder::class);
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

    private function payableVisit()
    {
        return $this->makeVisit([
            'status' => 'awaiting_payment',
            'checked_in_at' => now()->subHour(),
            'completed_at' => null,
        ]);
    }

    public function test_card_payment_without_reference_is_rejected(): void
    {
        $visit = $this->payableVisit();

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/payments", [
                'amount' => 10.0, 'kind' => 'consultation', 'method' => 'card',
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'field' => 'reference_no']);

        $this->assertDatabaseCount('visit_payments', 0);
    }

    public function test_card_payment_with_reference_is_recorded(): void
    {
        $visit = $this->payableVisit();

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/payments", [
                'amount' => 10.0, 'kind' => 'consultation', 'method' => 'card', 'reference_no' => 'TXN-12345',
            ])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('visit_payments', [
            'visit_id' => $visit->id, 'method' => 'card', 'reference_no' => 'TXN-12345', 'status' => 'paid',
        ]);
    }

    public function test_cash_payment_needs_no_reference(): void
    {
        $visit = $this->payableVisit();

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/payments", [
                'amount' => 10.0, 'kind' => 'consultation', 'method' => 'cash',
            ])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('visit_payments', [
            'visit_id' => $visit->id, 'method' => 'cash', 'status' => 'paid',
        ]);
    }

    public function test_payment_link_requires_a_configured_gateway(): void
    {
        // The fixture branch has no GatewayAccount → link creation must fail
        // cleanly (422), not 500.
        $visit = $this->payableVisit();

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/payment-link", ['amount' => 10.0])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_whatsapp_send_mints_server_side_and_needs_a_gateway(): void
    {
        // No client URL is accepted — the endpoint mints the link for the visit
        // itself, so with no gateway configured it fails cleanly (422), proving
        // it never just relays an arbitrary URL.
        $visit = $this->payableVisit();

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/payment-link/whatsapp", [
                'url' => 'https://evil.example.com/phish', // must be ignored
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_whatsapp_send_blocked_when_visit_not_payable(): void
    {
        // A completed visit no longer accepts payments → no link push.
        $visit = $this->makeVisit(['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($this->adminUser())
            ->postJson("/admin/v2/api/visits/{$visit->id}/payment-link/whatsapp", [])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_payment_method_cannot_duplicate_in_same_scope(): void
    {
        // The seeder already created a global 'cash'. A second one must be blocked.
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        \App\Models\ClinicPaymentMethod::create([
            'partner_id' => null, 'branch_id' => null,
            'key' => 'cash', 'label' => 'Cash 2', 'type' => 'manual',
            'requires_reference' => false, 'is_active' => true,
        ]);
    }
}
