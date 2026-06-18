<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\DepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
    }

    private function makeAsset(array $attrs = []): FixedAsset
    {
        return FixedAsset::create(array_merge([
            'code' => 'FA-'.uniqid(),
            'name' => 'Laser Device',
            'category' => 'medical_equipment',
            'asset_account_id' => $this->account('1210')->id,
            'accumulated_depreciation_account_id' => $this->account('1215')->id,
            'depreciation_expense_account_id' => $this->account('6610')->id,
            'cost' => 1200.000,
            'salvage_value' => 0,
            'useful_life_months' => 12,
            'in_service_date' => Carbon::parse('2026-01-15'),
            'status' => FixedAsset::STATUS_ACTIVE,
        ], $attrs));
    }

    public function test_monthly_depreciation_posts_balanced_entry(): void
    {
        $asset = $this->makeAsset(); // 1200 / 12 = 100 per month

        $svc = app(DepreciationService::class);
        $r = $svc->runForMonth(Carbon::parse('2026-02-01'));

        $this->assertSame(1, $r['posted']);
        $this->assertEqualsWithDelta(100.0, $r['total'], 0.001);

        $asset->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $asset->accumulated_depreciation, 0.001);

        // Dr 6610 expense / Cr 1215 accumulated depreciation.
        $entry = JournalEntry::query()->where('narration', 'like', 'Depreciation 2026-02%')->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->isBalanced());
        $this->assertBooksBalance();
    }

    public function test_depreciation_run_is_idempotent_per_month(): void
    {
        $asset = $this->makeAsset();
        $svc = app(DepreciationService::class);

        $svc->runForMonth(Carbon::parse('2026-02-01'));
        $svc->runForMonth(Carbon::parse('2026-02-01')); // re-run same month

        $asset->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $asset->accumulated_depreciation, 0.001, 'No double charge on re-run');
        $this->assertSame(1, $asset->depreciations()->count());
    }

    public function test_depreciation_stops_at_book_value_and_marks_fully_depreciated(): void
    {
        $asset = $this->makeAsset(['cost' => 120.000, 'useful_life_months' => 12]); // 10/mo
        $svc = app(DepreciationService::class);

        // Run 13 months — should cap at 120 total and never go negative.
        for ($m = 1; $m <= 13; $m++) {
            $svc->runForMonth(Carbon::parse('2026-01-01')->addMonths($m));
        }

        $asset->refresh();
        $this->assertEqualsWithDelta(120.0, (float) $asset->accumulated_depreciation, 0.001);
        $this->assertSame(0.0, $asset->netBookValue());
        $this->assertSame(FixedAsset::STATUS_FULLY_DEPRECIATED, $asset->status);
        $this->assertBooksBalance();
    }
}
