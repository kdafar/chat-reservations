<?php

namespace Database\Seeders;

use App\Models\Accounting\Vendor;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Services\Clinic\PurchaseService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Purchase-backed inventory for the Eureka demo.
 *
 * Before this ran, every branch's stock was a flat 1000/item with ZERO stock
 * movements — inventory that appeared from nowhere, with no purchasing or GL
 * trail. This seeder rebuilds stock the real way, through the Purchase-to-Pay
 * module:
 *
 *   create PO -> submit -> approve -> send -> receive (into stock + Dr Inventory
 *   / Cr Accounts Payable) -> pay vendor (Dr AP / Cr Cash)
 *
 * Result: real purchase_orders / receipts / payments, real clinic_stock_movements
 * (type=purchase_in), on-hand quantities that equal what was received, inventory
 * & AP journal entries, and a realistic mix of paid / partially-paid / open
 * vendor bills for the AP aging report.
 *
 * Run AFTER EurekaDemoSeeder (needs its 12 branches). Idempotent: wipes prior
 * purchasing data + demo vendors and rebuilds.
 *
 *   php artisan db:seed --class=EurekaPurchasingSeeder
 */
class EurekaPurchasingSeeder extends Seeder
{
    private const TZ = 'Asia/Kuwait';

    private const AP_SUPPLIERS_ACCOUNT = 28; // chart_of_accounts code 2110

    /** Realistic Kuwait medical-supply vendors. */
    private const VENDORS = [
        ['name' => 'Gulf Medical Supplies Co.', 'code' => 'GMS', 'terms' => 30, 'phone' => '+96522445566', 'country' => 'KW'],
        ['name' => 'Al-Mazaya Pharma Distribution', 'code' => 'MAZ', 'terms' => 45, 'phone' => '+96524556677', 'country' => 'KW'],
        ['name' => 'Kuwait Scientific Trading', 'code' => 'KST', 'terms' => 60, 'phone' => '+96525667788', 'country' => 'KW'],
        ['name' => 'Warba Medical Equipment', 'code' => 'WME', 'terms' => 30, 'phone' => '+96523778899', 'country' => 'KW'],
    ];

    public function run(): void
    {
        $this->command->info('=== EurekaPurchasingSeeder: purchase-backed inventory ===');

        /** @var PurchaseService $svc */
        $svc = app(PurchaseService::class);

        $this->wipePurchasing();
        $vendorIds = $this->seedVendors();

        // Reset the fabricated flat stock so on-hand ends up equal to what we
        // actually receive through purchases.
        DB::table('clinic_item_stocks')->update(['qty_on_hand_base' => 0]);

        $stockables = ClinicItem::query()->withoutGlobalScopes()
            ->where('is_stockable', true)->where('is_active', true)
            ->get(['id', 'default_cost'])->values();

        if ($stockables->isEmpty()) {
            $this->command->warn('No stockable items — nothing to purchase.');

            return;
        }

        $half = (int) ceil($stockables->count() / 2);
        $groupA = $stockables->slice(0, $half)->values();   // supplied by vendor A
        $groupB = $stockables->slice($half)->values();      // supplied by vendor B

        $branches = Branch::query()->withoutGlobalScopes()->orderBy('id')->get(['id']);
        $today = Carbon::today(self::TZ);

        $poCount = 0;
        $payCount = 0;
        $unpaid = 0;
        $partial = 0;

        foreach ($branches as $bi => $branch) {
            // Two POs per branch, one per supplier, covering the full catalog.
            $plan = [
                [$vendorIds[$bi % count($vendorIds)], $groupA],
                [$vendorIds[($bi + 1) % count($vendorIds)], $groupB],
            ];

            foreach ($plan as [$vendorId, $items]) {
                $orderDate = $today->copy()->subDays(random_int(18, 38));

                // ----- Draft PO -----
                Carbon::setTestNow($orderDate->copy()->setTime(9, 0));
                $lines = $items->map(fn ($it) => [
                    'clinic_item_id' => (int) $it->id,
                    // Order more of the cheap consumables, fewer of the costly
                    // vials — keeps a branch's inventory value realistic (a few
                    // thousand KWD), not warehouse-scale.
                    'qty_ordered' => (float) $this->orderQty((float) $it->default_cost),
                    // Real invoices wobble a few % around the standard cost.
                    'unit_cost' => round(max(0.05, (float) $it->default_cost * (random_int(92, 108) / 100)), 3),
                    'discount_type' => 'percent',
                    'discount_value' => random_int(0, 100) < 20 ? (float) random_int(2, 8) : 0.0,
                ])->all();

                $po = $svc->create($vendorId, (int) $branch->id, $lines, [
                    'payment_terms_days' => 30,
                    'notes' => 'Restock — opening inventory',
                ]);

                // ----- Lifecycle -----
                $svc->submit($po);
                $svc->approve($po);
                $svc->send($po);

                // ----- Receive in full a day or two later -----
                Carbon::setTestNow($orderDate->copy()->addDays(random_int(1, 4))->setTime(11, 0));
                $po->load('lines');
                $receiveLines = $po->lines->map(fn ($l) => [
                    'purchase_order_line_id' => $l->id,
                    'qty' => (float) $l->qty_ordered,
                ])->all();
                $svc->receive($po->refresh(), $receiveLines);
                $po->refresh();
                $poCount++;

                // ----- Pay: 60% full, 20% partial, 20% left open (AP aging) -----
                $roll = random_int(1, 100);
                $outstanding = $po->outstanding();
                if ($outstanding > 0.005) {
                    $payDate = $orderDate->copy()->addDays(random_int(6, 20));
                    if ($payDate->gt($today)) {
                        $payDate = $today->copy();
                    }
                    Carbon::setTestNow($payDate->copy()->setTime(13, 0));

                    if ($roll <= 60) {
                        $svc->pay($po, ['amount' => round($outstanding, 3), 'method' => 'bank_transfer', 'payment_date' => $payDate->toDateString()]);
                        $payCount++;
                    } elseif ($roll <= 80) {
                        $svc->pay($po, ['amount' => round($outstanding * (random_int(45, 75) / 100), 3), 'method' => 'bank_transfer', 'payment_date' => $payDate->toDateString()]);
                        $payCount++;
                        $partial++;
                    } else {
                        $unpaid++;
                    }
                }
            }

            $this->command->info("  branch {$branch->id}: 2 POs received into stock");
        }

        Carbon::setTestNow(); // reset

        $this->command->info("POs: {$poCount} | payments: {$payCount} (partial {$partial}) | left open: {$unpaid}");

        // Received stock is only half a stock system — without re-order points
        // the low-stock filter and LowStockNotification have nothing to compare
        // against, so derive them from the quantities just received.
        $this->call(StockThresholdSeeder::class);

        $this->report();
        $this->command->info('=== EurekaPurchasingSeeder done ===');
    }

    /** A realistic order quantity given the item's unit cost (cheap → bulk). */
    private function orderQty(float $cost): int
    {
        return match (true) {
            $cost < 2 => random_int(30, 120),
            $cost < 15 => random_int(10, 40),
            $cost < 50 => random_int(4, 18),
            default => random_int(1, 6),
        };
    }

    private function wipePurchasing(): void
    {
        // Remove GL entries this module posted (receipts + payments), incl. any
        // orphaned by a prior run, so re-seeding never double-counts inventory.
        $sourceTypes = [\App\Models\Purchasing\PurchaseReceipt::class, \App\Models\Purchasing\PurchasePayment::class];
        $jeIds = DB::table('journal_entries')->whereIn('source_type', $sourceTypes)->pluck('id');
        if ($jeIds->isNotEmpty()) {
            DB::table('journal_entry_lines')->whereIn('journal_entry_id', $jeIds)->delete();
            DB::table('journal_entries')->whereIn('id', $jeIds)->delete();
        }

        // Clear the stock-movement ledger rows this module wrote, so re-seeding
        // doesn't leave orphaned purchase_in entries pointing at deleted receipts.
        DB::table('clinic_stock_movements')
            ->where('type', 'purchase_in')
            ->orWhere('related_type', \App\Models\Purchasing\PurchaseReceipt::class)
            ->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['purchase_payments', 'purchase_receipt_lines', 'purchase_receipts', 'purchase_order_lines', 'purchase_orders', 'vendors'] as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                DB::table($t)->delete();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedVendors(): array
    {
        $ids = [];
        foreach (self::VENDORS as $v) {
            $vendor = Vendor::create([
                'name' => $v['name'],
                'code' => $v['code'],
                'contact_name' => 'Procurement Desk',
                'phone' => $v['phone'],
                'email' => strtolower($v['code']).'@vendors.eureka.demo',
                'address' => 'Shuwaikh Industrial, Kuwait',
                'default_currency' => 'KWD',
                'country' => $v['country'],
                'default_payment_terms_days' => $v['terms'],
                'default_payable_account_id' => self::AP_SUPPLIERS_ACCOUNT,
                'is_active' => true,
            ]);
            $ids[] = $vendor->id;
        }

        return $ids;
    }

    private function report(): void
    {
        $mv = DB::table('clinic_stock_movements')->where('type', 'purchase_in')->count();
        $onHand = DB::table('clinic_item_stocks')->where('qty_on_hand_base', '>', 0)->count();
        $recv = DB::table('purchase_receipts')->sum('total_amount');
        $paid = DB::table('purchase_payments')->sum('amount');
        $this->command->info("  purchase_in movements: {$mv} | stock rows > 0: {$onHand}");
        $this->command->info('  received value: '.number_format((float) $recv, 3).' KWD | paid to vendors: '.number_format((float) $paid, 3).' KWD');
        $this->command->info('  outstanding AP: '.number_format((float) $recv - (float) $paid, 3).' KWD');
    }
}
