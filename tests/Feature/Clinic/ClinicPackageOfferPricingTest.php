<?php

namespace Tests\Feature\Clinic;

use App\Models\ClinicPackage;
use App\Models\VisitPackage;
use App\Services\Clinic\VisitPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Offer pricing on clinic packages: `default_price` is the main price and
 * `discount_price` is the offer. The saving the patient is shown on the public
 * site and the saving they actually get billed must be the same number, so both
 * are derived from the model accessors tested here.
 */
class ClinicPackageOfferPricingTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected VisitPackageService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(VisitPackageService::class);
        config(['clinic.packages_enabled' => true]);
    }

    public function test_discount_price_produces_effective_price_and_savings(): void
    {
        $pkg = $this->makeClinicPackage(['default_price' => 100.000, 'discount_price' => 70.000]);

        $this->assertTrue($pkg->has_discount);
        $this->assertEqualsWithDelta(70.0, $pkg->effective_price, 0.001);
        $this->assertEqualsWithDelta(30.0, $pkg->savings_amount, 0.001);
        $this->assertSame(30, $pkg->savings_percent);
    }

    public function test_package_without_discount_charges_the_main_price(): void
    {
        $pkg = $this->makeClinicPackage(['default_price' => 45.000]);

        $this->assertFalse($pkg->has_discount);
        $this->assertEqualsWithDelta(45.0, $pkg->effective_price, 0.001);
        $this->assertEqualsWithDelta(0.0, $pkg->savings_amount, 0.001);
        $this->assertSame(0, $pkg->savings_percent);
    }

    public function test_expired_offer_falls_back_to_the_main_price(): void
    {
        $pkg = $this->makeClinicPackage([
            'default_price' => 100.000,
            'discount_price' => 70.000,
            'offer_ends_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($pkg->has_discount, 'an ended offer must not keep discounting');
        $this->assertEqualsWithDelta(100.0, $pkg->effective_price, 0.001);
        $this->assertEqualsWithDelta(0.0, $pkg->savings_amount, 0.001);
    }

    public function test_offer_that_has_not_started_yet_is_not_live(): void
    {
        $pkg = $this->makeClinicPackage([
            'default_price' => 100.000,
            'discount_price' => 70.000,
            'offer_starts_at' => now()->addWeek()->toDateString(),
        ]);

        $this->assertFalse($pkg->has_discount);
        $this->assertEqualsWithDelta(100.0, $pkg->effective_price, 0.001);
    }

    public function test_visit_is_billed_the_offer_price_and_shows_the_saving(): void
    {
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $pkg = $this->makeClinicPackage(['default_price' => 100.000, 'discount_price' => 70.000]);

        $this->svc->applyPackagesOnly($visit, [
            ['clinic_package_id' => $pkg->id, 'qty' => 1],
        ]);

        $row = VisitPackage::where('visit_id', $visit->id)->first();

        // The main price stays on the line so the bill can show "was 100",
        // and the saving rides in discount_amount, which the totals net off.
        $this->assertEqualsWithDelta(100.0, (float) $row->unit_price_snapshot, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $row->line_total, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $row->discount_amount, 0.001);
        $this->assertSame('offer', $row->discount_source);
        $this->assertEqualsWithDelta(
            70.0,
            (float) $row->line_total - (float) $row->discount_amount,
            0.001,
            'patient must be charged the offer price'
        );
    }

    public function test_saving_scales_with_quantity(): void
    {
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $pkg = $this->makeClinicPackage(['default_price' => 100.000, 'discount_price' => 70.000]);

        $this->svc->applyPackagesOnly($visit, [['clinic_package_id' => $pkg->id, 'qty' => 1]]);
        $this->svc->applyPackagesOnly($visit, [['clinic_package_id' => $pkg->id, 'qty' => 2]]);

        $row = VisitPackage::where('visit_id', $visit->id)->first();

        $this->assertEqualsWithDelta(3.0, (float) $row->qty, 0.001);
        $this->assertEqualsWithDelta(300.0, (float) $row->line_total, 0.001);
        $this->assertEqualsWithDelta(90.0, (float) $row->discount_amount, 0.001);
        $this->assertEqualsWithDelta(
            210.0,
            (float) $row->line_total - (float) $row->discount_amount,
            0.001,
            '3 packages at the 70 offer price'
        );
    }

    public function test_public_offers_scope_only_returns_published_live_packages(): void
    {
        $published = $this->makeClinicPackage([
            'default_price' => 100.000, 'discount_price' => 80.000, 'is_public' => true,
        ]);
        $this->makeClinicPackage([
            'default_price' => 100.000, 'discount_price' => 80.000, 'is_public' => false,
        ]);
        $this->makeClinicPackage([
            'default_price' => 100.000, 'discount_price' => 80.000, 'is_public' => true,
            'is_active' => false,
        ]);
        $this->makeClinicPackage([
            'default_price' => 100.000, 'discount_price' => 80.000, 'is_public' => true,
            'offer_ends_at' => now()->subDay()->toDateString(),
        ]);

        $ids = ClinicPackage::query()->withoutGlobalScopes()->publicOffers()->pluck('id')->all();

        $this->assertSame([$published->id], $ids);
    }
}
