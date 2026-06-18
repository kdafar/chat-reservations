<?php

namespace Tests\Feature\V2;

use App\Models\Accounting\PrepaidSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Server-side account-type enforcement for the prepaid-expense register —
 * the mirror of FixedAssetValidationTest. A crafted request must not be able
 * to point the prepaid asset at a non-asset, or the release target at a
 * non-expense, account.
 */
class PrepaidScheduleValidationTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    private function accountant(): User
    {
        Role::findOrCreate('admin', 'web');
        foreach (['view_any_accounting_accounts', 'update_accounting_accounts'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::create(['name' => 'Acc', 'email' => 'acc-'.uniqid().'@t.local', 'password' => Hash::make('password'), 'status' => 'active']);
        $u->assignRole('admin');
        $u->givePermissionTo('view_any_accounting_accounts', 'update_accounting_accounts');

        return $u->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Annual clinic rent (prepaid)',
            'prepaid_account_id' => $this->account('1160')->id, // Prepaid Rent (asset) ✓
            'expense_account_id' => $this->account('6210')->id,  // Rent — Clinic (expense) ✓
            'total_amount' => 1200,
            'term_months' => 12,
            'start_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_rejects_wrong_account_types(): void
    {
        $this->seedChartOfAccounts();

        // Prepaid asset pointed at an EXPENSE account → rejected.
        $this->actingAs($this->accountant())
            ->post('/admin/v2/accounting/prepaid-schedules', $this->payload(['prepaid_account_id' => $this->account('6210')->id]))
            ->assertSessionHasErrors('prepaid_account_id');

        // Expense target pointed at an ASSET account → rejected.
        $this->actingAs($this->accountant())
            ->post('/admin/v2/accounting/prepaid-schedules', $this->payload(['expense_account_id' => $this->account('1160')->id]))
            ->assertSessionHasErrors('expense_account_id');

        $this->assertSame(0, PrepaidSchedule::query()->count(), 'No schedule should be created from invalid payloads');
    }

    public function test_accepts_correct_account_types(): void
    {
        $this->seedChartOfAccounts();

        $this->actingAs($this->accountant())
            ->post('/admin/v2/accounting/prepaid-schedules', $this->payload())
            ->assertRedirect(route('v2.prepaid-schedules.index'));

        $this->assertSame(1, PrepaidSchedule::query()->count());
    }
}
