<?php

namespace Tests\Feature\V2;

use App\Models\Accounting\FixedAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * The fixed-asset register's account pickers are type-filtered in the UI; this
 * locks the SERVER-SIDE enforcement so a crafted request can't wire a wrong
 * account type (e.g. a revenue account as the depreciation expense target).
 */
class FixedAssetValidationTest extends TestCase
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
            'name' => 'Laser device',
            'category' => 'medical_equipment',
            'asset_account_id' => $this->account('1210')->id,                 // asset ✓
            'accumulated_depreciation_account_id' => $this->account('1215')->id, // contra-asset ✓
            'depreciation_expense_account_id' => $this->account('6610')->id,   // expense ✓
            'cost' => 1200,
            'salvage_value' => 0,
            'useful_life_months' => 12,
            'in_service_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_rejects_wrong_account_types(): void
    {
        $this->seedChartOfAccounts();

        // depreciation expense pointed at a REVENUE account → must be rejected.
        $resp = $this->actingAs($this->accountant())->post('/admin/v2/accounting/fixed-assets', $this->payload([
            'depreciation_expense_account_id' => $this->account('4110')->id,
        ]));
        $resp->assertSessionHasErrors('depreciation_expense_account_id');

        // asset account pointed at an EXPENSE account → rejected.
        $resp2 = $this->actingAs($this->accountant())->post('/admin/v2/accounting/fixed-assets', $this->payload([
            'asset_account_id' => $this->account('6610')->id,
        ]));
        $resp2->assertSessionHasErrors('asset_account_id');

        $this->assertSame(0, FixedAsset::query()->count(), 'No asset should be created from invalid payloads');
    }

    public function test_accepts_correct_account_types(): void
    {
        $this->seedChartOfAccounts();

        $resp = $this->actingAs($this->accountant())->post('/admin/v2/accounting/fixed-assets', $this->payload());
        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect(route('v2.fixed-assets.index'));

        $this->assertSame(1, FixedAsset::query()->count());
    }
}
