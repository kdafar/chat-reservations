<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\ClinicItem;
use App\Models\ClinicStockMovement;
use App\Models\DoctorCompensationLedger;
use App\Models\VisitPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccountingChartOfAccounts;
use Tests\TestCase;

/**
 * Verifies that model observers fire and produce GL entries WITHOUT any
 * manual AccountingService call. This is the contract the rest of the
 * clinic flow relies on: "every clinic event that moves money posts."
 */
class AutoPostingObserverTest extends TestCase
{
    use RefreshDatabase, SeedsAccountingChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedChartOfAccounts();
        $this->seedClinicFixtures();
        app(\App\Services\Accounting\ChartOfAccounts::class)->refresh();
    }

    public function test_creating_a_paid_visit_payment_auto_posts(): void
    {
        $visit = $this->makeVisit();

        $before = JournalEntry::count();
        VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 30.000,
            'method' => 'cash',
            'status' => 'paid',
            'kind' => 'consultation',
            'paid_at' => now(),
        ]);

        $this->assertSame($before + 1, JournalEntry::count(), 'Observer should auto-create one JE');
        $this->assertBooksBalance();
    }

    public function test_refunding_a_payment_auto_reverses(): void
    {
        $visit = $this->makeVisit();
        $payment = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 25.000, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);

        $this->assertSame(1, JournalEntry::count());

        $payment->update(['status' => 'refunded']);

        // Two entries exist now: the original (reversed) + the reversal
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame(1, JournalEntry::where('status', JournalEntry::STATUS_REVERSED)->count());
        $this->assertSame(1, JournalEntry::where('status', JournalEntry::STATUS_POSTED)->count());

        $this->assertBooksBalance();
        // Net cash account balance should be zero
        $this->assertEqualsWithDelta(0.0, $this->account('1110')->balanceAt(now()->toDateString()), 0.001);
    }

    public function test_stock_consume_movement_auto_posts(): void
    {
        $f = $this->seedClinicFixtures();
        $item = ClinicItem::create([
            'name' => ['en' => 'Drug'],
            'default_cost' => 4.000,
            'default_price' => 12.000,
            'is_stockable' => true,
            'is_billable' => true,
            'is_active' => true,
            'type' => 'consumable',
        ]);

        $before = JournalEntry::count();
        ClinicStockMovement::create([
            'branch_id' => $f['branch']->id,
            'clinic_item_id' => $item->id,
            'type' => 'consume',
            'qty_change_base' => -2,
            'before_qty_base' => 10,
            'after_qty_base' => 8,
        ]);

        $this->assertSame($before + 1, JournalEntry::count());
        $this->assertBooksBalance();
    }

    public function test_doctor_compensation_save_auto_posts(): void
    {
        $f = $this->seedClinicFixtures();
        $visit = $this->makeVisit();

        $before = JournalEntry::count();
        DoctorCompensationLedger::create([
            'visit_id' => $visit->id,
            'doctor_id' => $f['doctor']->id,
            'branch_id' => $f['branch']->id,
            'doctor_cut_amount' => 5.500,
            'fees_snapshot' => 25,
        ]);

        $this->assertSame($before + 1, JournalEntry::count());
        $this->assertBooksBalance();
    }

    public function test_re_saving_payment_does_not_double_post(): void
    {
        $visit = $this->makeVisit();
        $payment = VisitPayment::create([
            'visit_id' => $visit->id,
            'amount' => 25, 'method' => 'cash', 'status' => 'paid',
            'kind' => 'consultation', 'paid_at' => now(),
        ]);

        $count = JournalEntry::count();

        // Touch the model multiple times — observer should NOT double-post
        $payment->touch();
        $payment->update(['reference_no' => 'TEST']);

        $this->assertSame($count, JournalEntry::count());
    }
}
