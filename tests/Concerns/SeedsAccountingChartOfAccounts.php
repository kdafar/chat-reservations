<?php

namespace Tests\Concerns;

use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\Accounting\ChartOfAccounts;
use Carbon\Carbon;
use Database\Seeders\AccountingChartOfAccountsSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Shared test fixtures for the accounting suite.
 *
 * Use with `RefreshDatabase` so the migration set is freshly applied to an
 * in-memory SQLite DB. Seeds the Kuwait clinic Chart of Accounts and exposes
 * factory-style helpers so every test class can spin up a minimal clinic
 * fixture (partner → branch → doctor → patient → visit) without copy-paste.
 */
trait SeedsAccountingChartOfAccounts
{
    protected ?Partner $partnerFixture = null;

    protected ?Branch $branchFixture = null;

    protected ?Doctor $doctorFixture = null;

    protected ?Patient $patientFixture = null;

    protected ?User $userFixture = null;

    /**
     * Seed the Chart of Accounts. Call from a test's setUp() after parent::setUp().
     * Re-callable safely (the seeder is idempotent).
     */
    protected function seedChartOfAccounts(): void
    {
        $this->seed(AccountingChartOfAccountsSeeder::class);
        // Refresh the cached ChartOfAccounts lookup since the seeder ran inside the test.
        app(ChartOfAccounts::class)->refresh();
    }

    /**
     * Spin up the minimal fixture chain: partner → branch → doctor → patient → user.
     * Memoizes so multiple calls return the same fixture set.
     */
    protected function seedClinicFixtures(): array
    {
        if ($this->branchFixture && $this->doctorFixture && $this->patientFixture) {
            return [
                'partner' => $this->partnerFixture,
                'branch' => $this->branchFixture,
                'doctor' => $this->doctorFixture,
                'patient' => $this->patientFixture,
                'user' => $this->userFixture,
            ];
        }

        $this->userFixture = User::create([
            'name' => 'Test Admin',
            'email' => 'test-admin-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
        ]);

        $this->partnerFixture = Partner::create([
            'name' => 'Test Clinic Partner',
            'slug' => 'test-clinic-'.uniqid(),
        ]);

        $this->branchFixture = Branch::create([
            'partner_id' => $this->partnerFixture->id,
            'name' => ['en' => 'Test Branch', 'ar' => 'فرع'],
            'slug' => 'test-branch-'.uniqid(),
            'is_available' => true,
        ]);

        $this->doctorFixture = Doctor::create([
            'partner_id' => $this->partnerFixture->id,
            'branch_id' => $this->branchFixture->id,
            'name' => 'Dr. Test',
            'specialty' => 'General Practice',
            'consultation_fee' => 25.000,
            'is_active' => true,
        ]);

        $this->patientFixture = Patient::create([
            'partner_id' => $this->partnerFixture->id,
            'name' => 'Test Patient',
            'phone' => '+96599'.random_int(100000, 999999),
            'gender' => 'male',
        ]);

        return [
            'partner' => $this->partnerFixture,
            'branch' => $this->branchFixture,
            'doctor' => $this->doctorFixture,
            'patient' => $this->patientFixture,
            'user' => $this->userFixture,
        ];
    }

    /**
     * Build (and save) a completed Visit for the seeded fixtures.
     * Override $attributes to set status, dates, etc.
     */
    protected function makeVisit(array $attributes = []): Visit
    {
        $f = $this->seedClinicFixtures();

        return Visit::create(array_merge([
            'patient_id' => $f['patient']->id,
            'doctor_id' => $f['doctor']->id,
            'branch_id' => $f['branch']->id,
            'status' => Visit::STATUS_COMPLETED,
            'checked_in_at' => Carbon::now()->subHour(),
            'completed_at' => Carbon::now(),
        ], $attributes));
    }

    /**
     * Look up an Account by its CoA code. Throws if not seeded.
     */
    protected function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }

    /**
     * Build a stockable clinic item with sensible defaults.
     */
    protected function makeClinicItem(array $attributes = []): \App\Models\ClinicItem
    {
        return \App\Models\ClinicItem::create(array_merge([
            'name' => ['en' => 'Test Item '.uniqid()],
            'type' => 'consumable',
            'default_cost' => 2.000,
            'default_price' => 5.000,
            'is_stockable' => true,
            'is_billable' => true,
            'is_active' => true,
            'conversion_factor' => 1.0,
        ], $attributes));
    }

    /**
     * Build a clinic package with the given items.
     *
     * @param  array<array{item: \App\Models\ClinicItem, qty: float}>  $items
     */
    protected function makeClinicPackage(array $attributes, array $items = []): \App\Models\ClinicPackage
    {
        $f = $this->seedClinicFixtures();

        $package = \App\Models\ClinicPackage::create(array_merge([
            'branch_id' => $f['branch']->id,
            'name' => ['en' => 'Test Package '.uniqid()],
            'default_price' => 30.000,
            'is_active' => true,
        ], $attributes));

        foreach ($items as $row) {
            \App\Models\ClinicPackageItem::create([
                'clinic_package_id' => $package->id,
                'clinic_item_id' => $row['item']->id,
                'qty_base' => (float) ($row['qty'] ?? 1),
                'is_consumable' => true,
            ]);
        }

        return $package->fresh('items');
    }

    /**
     * Seed stock for a clinic item at the fixture branch.
     */
    protected function makeStock(\App\Models\ClinicItem $item, float $qty = 100.0, ?int $branchId = null): \App\Models\ClinicItemStock
    {
        $f = $this->seedClinicFixtures();

        return \App\Models\ClinicItemStock::updateOrCreate(
            [
                'branch_id' => $branchId ?? $f['branch']->id,
                'clinic_item_id' => $item->id,
            ],
            ['qty_on_hand_base' => $qty]
        );
    }

    /**
     * Convenience: total of all posted debits across the GL.
     */
    protected function totalPostedDebits(): float
    {
        return (float) \DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->sum('debit');
    }

    /**
     * Convenience: total of all posted credits across the GL.
     */
    protected function totalPostedCredits(): float
    {
        return (float) \DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->sum('credit');
    }

    /**
     * Assert the General Ledger as a whole balances.
     * Use after any test that posts entries.
     */
    protected function assertBooksBalance(string $message = ''): void
    {
        $debit = $this->totalPostedDebits();
        $credit = $this->totalPostedCredits();
        $this->assertEqualsWithDelta(
            $debit,
            $credit,
            0.01,
            $message ?: "Books out of balance: Dr={$debit} Cr={$credit}"
        );
    }
}
