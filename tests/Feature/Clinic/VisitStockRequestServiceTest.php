<?php

namespace Tests\Feature\Clinic;

use App\Models\VisitItem;
use App\Models\VisitStockRequest;
use App\Models\VisitStockRequestLine;
use App\Services\Clinic\VisitStockRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

class VisitStockRequestServiceTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected VisitStockRequestService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        $this->svc = app(VisitStockRequestService::class);
        config([
            'clinic.stock_requests_enabled' => true,
            'clinic.inventory_enabled' => true,
        ]);
    }

    public function test_create_for_visit_persists_price_snapshot(): void
    {
        // Audit fix #8: lines must snapshot unit_cost / unit_price at request time.
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $item = $this->makeClinicItem([
            'default_cost' => 4.500,
            'default_price' => 12.000,
        ]);

        $req = $this->svc->createForVisit($visit, [
            ['clinic_item_id' => $item->id, 'qty_base' => 3],
        ]);

        $line = VisitStockRequestLine::where('visit_stock_request_id', $req->id)
            ->where('clinic_item_id', $item->id)
            ->first();

        $this->assertNotNull($line);
        $this->assertEqualsWithDelta(3.0, (float) $line->qty_base, 0.001);
        $this->assertEqualsWithDelta(4.500, (float) $line->unit_cost_snapshot, 0.001);
        $this->assertEqualsWithDelta(12.000, (float) $line->unit_price_snapshot, 0.001);
    }

    public function test_create_for_visit_sets_status_awaiting_stock(): void
    {
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $item = $this->makeClinicItem();

        $this->svc->createForVisit($visit, [
            ['clinic_item_id' => $item->id, 'qty_base' => 1],
        ]);

        $visit->refresh();
        $this->assertSame('awaiting_stock', $visit->status);
    }

    public function test_fulfill_consumes_stock_and_adds_visit_item(): void
    {
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $item = $this->makeClinicItem([
            'default_cost' => 2.000,
            'default_price' => 8.000,
        ]);
        $this->makeStock($item, 50);

        $req = $this->svc->createForVisit($visit, [
            ['clinic_item_id' => $item->id, 'qty_base' => 5],
        ]);

        $this->svc->fulfill($req);

        $req->refresh();
        $this->assertSame(VisitStockRequest::STATUS_FULFILLED, $req->status);

        // visit_items row appears
        $vi = VisitItem::where('visit_id', $visit->id)->where('clinic_item_id', $item->id)->first();
        $this->assertNotNull($vi);
        $this->assertEqualsWithDelta(5.0, (float) $vi->qty, 0.001);
        $this->assertEqualsWithDelta(2.000, (float) $vi->unit_cost_snapshot, 0.001);
        $this->assertEqualsWithDelta(8.000, (float) $vi->unit_price_snapshot, 0.001);

        // Stock decremented
        $stock = $item->stocks()->where('branch_id', $visit->branch_id)->first();
        $this->assertEqualsWithDelta(45.0, (float) $stock->qty_on_hand_base, 0.001);
    }

    public function test_fulfill_honors_request_line_snapshot_not_current_item_price(): void
    {
        // Audit fix #8: even if item.default_price changes between request and
        // fulfillment, the patient's invoice must reflect the price at request time.
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $item = $this->makeClinicItem([
            'default_cost' => 2.000,
            'default_price' => 5.000,  // price at REQUEST time
        ]);
        $this->makeStock($item, 50);

        $req = $this->svc->createForVisit($visit, [
            ['clinic_item_id' => $item->id, 'qty_base' => 2],
        ]);

        // Admin changes the item price AFTER the request was created
        $item->update(['default_price' => 99.999]);

        $this->svc->fulfill($req);

        $vi = VisitItem::where('visit_id', $visit->id)->where('clinic_item_id', $item->id)->first();
        $this->assertEqualsWithDelta(5.000, (float) $vi->unit_price_snapshot, 0.001,
            'visit_item must use the snapshot price (5.000), not the new live price (99.999)');
    }

    public function test_cancel_returns_visit_to_awaiting_doctor(): void
    {
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $item = $this->makeClinicItem();

        $req = $this->svc->createForVisit($visit, [
            ['clinic_item_id' => $item->id, 'qty_base' => 1],
        ]);
        $this->assertSame('awaiting_stock', $visit->refresh()->status);

        $this->svc->cancel($req, 0, 'no longer needed');

        $req->refresh();
        $this->assertSame(VisitStockRequest::STATUS_CANCELLED, $req->status);
        $visit->refresh();
        $this->assertSame('awaiting_doctor', $visit->status);
    }

    public function test_issue_or_request_for_visit_issues_when_stock_available(): void
    {
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $item = $this->makeClinicItem([
            'default_cost' => 1.000, 'default_price' => 3.000,
        ]);
        $this->makeStock($item, 100);

        $result = $this->svc->issueOrRequestForVisit($visit, [
            ['clinic_item_id' => $item->id, 'qty_base' => 4],
        ]);

        $this->assertSame('issued', $result['mode']);

        // visit_items row appears (consume + upsertVisitItem)
        $vi = VisitItem::where('visit_id', $visit->id)->where('clinic_item_id', $item->id)->first();
        $this->assertNotNull($vi);
        $this->assertEqualsWithDelta(4.0, (float) $vi->qty, 0.001);
    }

    public function test_issue_or_request_for_visit_creates_request_when_short(): void
    {
        $visit = $this->makeVisit(['status' => 'in_progress']);
        $item = $this->makeClinicItem();
        $this->makeStock($item, 2);  // only 2 in stock

        $result = $this->svc->issueOrRequestForVisit($visit, [
            ['clinic_item_id' => $item->id, 'qty_base' => 10],
        ]);

        $this->assertSame('request', $result['mode']);
        $this->assertGreaterThan(0, $result['request_id']);

        $visit->refresh();
        $this->assertSame('awaiting_stock', $visit->status);
    }

    public function test_non_stockable_items_are_filtered_out_of_requirements(): void
    {
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $stockable = $this->makeClinicItem(['is_stockable' => true]);
        $service = $this->makeClinicItem(['is_stockable' => false, 'type' => 'service']);

        $req = $this->svc->createForVisit($visit, [
            ['clinic_item_id' => $stockable->id, 'qty_base' => 1],
            ['clinic_item_id' => $service->id, 'qty_base' => 1],
        ]);

        $lines = VisitStockRequestLine::where('visit_stock_request_id', $req->id)->get();
        $this->assertCount(1, $lines, 'Non-stockable items must not produce stock-request lines');
        $this->assertSame($stockable->id, $lines->first()->clinic_item_id);
    }

    public function test_create_for_visit_merges_with_existing_pending_lines(): void
    {
        // Audit follow-up review #1: adding package A then package B before
        // fulfilling A must NOT lose A's lines. The earlier impl used
        // updateOrCreate + delete-not-in-new-set which silently dropped
        // earlier package items.
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $itemA = $this->makeClinicItem();
        $itemB = $this->makeClinicItem();

        $this->svc->createForVisit($visit, [['clinic_item_id' => $itemA->id, 'qty_base' => 2]]);
        $this->svc->createForVisit($visit, [['clinic_item_id' => $itemB->id, 'qty_base' => 3]]);

        $req = VisitStockRequest::query()
            ->where('visit_id', $visit->id)
            ->where('status', VisitStockRequest::STATUS_PENDING)
            ->first();
        $lines = VisitStockRequestLine::where('visit_stock_request_id', $req->id)->get();

        $this->assertCount(2, $lines, 'Both packages must remain in the pending request');
        $byItem = $lines->keyBy('clinic_item_id');
        $this->assertEqualsWithDelta(2.0, (float) $byItem[$itemA->id]->qty_base, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) $byItem[$itemB->id]->qty_base, 0.001);
    }

    public function test_create_for_visit_increments_qty_when_same_item_added_twice(): void
    {
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $item = $this->makeClinicItem();

        $this->svc->createForVisit($visit, [['clinic_item_id' => $item->id, 'qty_base' => 4]]);
        $this->svc->createForVisit($visit, [['clinic_item_id' => $item->id, 'qty_base' => 1]]);

        $req = VisitStockRequest::where('visit_id', $visit->id)
            ->where('status', VisitStockRequest::STATUS_PENDING)->first();
        $line = VisitStockRequestLine::where('visit_stock_request_id', $req->id)
            ->where('clinic_item_id', $item->id)->first();

        $this->assertEqualsWithDelta(5.0, (float) $line->qty_base, 0.001,
            'Repeated calls for the same item must SUM the qty, not overwrite');
    }

    public function test_create_for_visit_preserves_price_snapshot_on_merge(): void
    {
        // Original snapshot must survive even if item.default_price changes
        // between createForVisit calls.
        $visit = $this->makeVisit(['status' => 'awaiting_doctor']);
        $item = $this->makeClinicItem(['default_price' => 5.000]);

        $this->svc->createForVisit($visit, [['clinic_item_id' => $item->id, 'qty_base' => 1]]);
        $item->update(['default_price' => 99.000]);
        $this->svc->createForVisit($visit, [['clinic_item_id' => $item->id, 'qty_base' => 2]]);

        $req = VisitStockRequest::where('visit_id', $visit->id)
            ->where('status', VisitStockRequest::STATUS_PENDING)->first();
        $line = VisitStockRequestLine::where('visit_stock_request_id', $req->id)
            ->where('clinic_item_id', $item->id)->first();

        $this->assertEqualsWithDelta(3.0, (float) $line->qty_base, 0.001);
        $this->assertEqualsWithDelta(5.000, (float) $line->unit_price_snapshot, 0.001,
            'Original price snapshot must survive subsequent merges');
    }
}
