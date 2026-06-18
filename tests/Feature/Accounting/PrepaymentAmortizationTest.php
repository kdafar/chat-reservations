<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\PrepaidSchedule;
use App\Services\Accounting\PrepaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class PrepaymentAmortizationTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
    }

    private function makeSchedule(array $attrs = []): PrepaidSchedule
    {
        return PrepaidSchedule::create(array_merge([
            'code' => 'PRE-'.uniqid(),
            'name' => 'Annual clinic rent (prepaid)',
            'prepaid_account_id' => $this->account('1160')->id,  // Prepaid Rent
            'expense_account_id' => $this->account('6210')->id,  // Rent — Clinic
            'total_amount' => 1200.000,
            'term_months' => 12,
            'start_date' => Carbon::parse('2026-01-01'),
            'status' => PrepaidSchedule::STATUS_ACTIVE,
        ], $attrs));
    }

    public function test_monthly_amortization_releases_prepaid_to_expense(): void
    {
        $schedule = $this->makeSchedule(); // 1200 / 12 = 100/mo

        $r = app(PrepaymentService::class)->runForMonth(Carbon::parse('2026-01-01'));

        $this->assertSame(1, $r['posted']);
        $this->assertEqualsWithDelta(100.0, $r['total'], 0.001);

        $entry = JournalEntry::query()->where('narration', 'like', 'Prepaid amortization 2026-01%')->first();
        $this->assertNotNull($entry);
        $debit = $entry->lines->where('debit', '>', 0)->first();
        $credit = $entry->lines->where('credit', '>', 0)->first();
        $this->assertSame('6210', $debit->account->code);
        $this->assertSame('1160', $credit->account->code);
        $this->assertBooksBalance();
    }

    public function test_amortization_is_idempotent_and_completes(): void
    {
        $schedule = $this->makeSchedule(['total_amount' => 120.000, 'term_months' => 12]);
        $svc = app(PrepaymentService::class);

        // 13 months + a duplicate run → exactly 120 released, status completed.
        for ($m = 0; $m <= 12; $m++) {
            $svc->runForMonth(Carbon::parse('2026-01-01')->addMonths($m));
        }
        $svc->runForMonth(Carbon::parse('2026-03-01')); // duplicate

        $schedule->refresh();
        $this->assertEqualsWithDelta(120.0, (float) $schedule->amortized_amount, 0.001);
        $this->assertSame(PrepaidSchedule::STATUS_COMPLETED, $schedule->status);
        $this->assertBooksBalance();
    }
}
