<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Models\Visit;
use App\Models\VisitStockRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (re-runnable) cleanup for the duplicate-per-clinic catalog seed mess:
 *   1. Drop stock rows recorded against another clinic's item (wrong catalog row).
 *   2. Ensure every branch's own-clinic stockable items have healthy stock so
 *      nothing reads as "short".
 *   3. Cancel pending stock requests orphaned by a removed package (visit now has
 *      no packages) and resume those visits off awaiting_stock.
 *
 * Safe on dev/seed data. Idempotent.
 */
class ClinicStockRealign extends Command
{
    protected $signature = 'clinic:stock-realign {--floor=200} {--topup=1000}';

    protected $description = 'Realign branch stock to the branch\'s own-clinic items, top up shortages, and cancel orphaned stock requests.';

    public function handle(): int
    {
        $floor = (float) $this->option('floor');
        $topup = (float) $this->option('topup');

        $branches = Branch::query()->get(['id', 'partner_id']);
        $droppedMisplaced = 0;
        $toppedUp = 0;

        foreach ($branches as $branch) {
            $partnerId = (int) ($branch->partner_id ?? 0);

            // 1) Drop stock recorded against an item belonging to a DIFFERENT clinic.
            $misplaced = ClinicItemStock::query()
                ->where('branch_id', $branch->id)
                ->whereHas('clinicItem', function ($q) use ($partnerId) {
                    $q->whereNotNull('partner_id')->where('partner_id', '!=', $partnerId);
                })
                ->get();
            foreach ($misplaced as $row) {
                $row->delete();
                $droppedMisplaced++;
            }

            // 2) Top up every own-clinic (or global) stockable item at this branch.
            $items = ClinicItem::query()
                ->where('is_active', true)
                ->where('is_stockable', true)
                ->where(function ($q) use ($partnerId) {
                    $q->whereNull('partner_id')->orWhere('partner_id', $partnerId);
                })
                ->where(function ($q) use ($branch) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branch->id);
                })
                ->get(['id']);

            foreach ($items as $item) {
                $stock = ClinicItemStock::firstOrNew([
                    'branch_id' => $branch->id,
                    'clinic_item_id' => $item->id,
                ]);
                if (! $stock->exists || (float) $stock->qty_on_hand_base < $floor) {
                    $stock->qty_on_hand_base = $topup;
                    $stock->save();
                    $toppedUp++;
                }
            }
        }

        // 3) Cancel pending stock requests orphaned by a removed package.
        $cancelled = 0;
        $orphans = VisitStockRequest::query()
            ->where('status', VisitStockRequest::STATUS_PENDING)
            ->whereDoesntHave('visit.visitPackages')
            ->with('visit')
            ->get();

        foreach ($orphans as $req) {
            DB::transaction(function () use ($req, &$cancelled) {
                $req->forceFill([
                    'status' => VisitStockRequest::STATUS_CANCELLED,
                    'notes' => trim((string) ($req->notes ?? '').' [auto] Package removed from visit'),
                ])->save();

                $visit = $req->visit;
                if ($visit && $visit->status === Visit::STATUS_AWAITING_STOCK) {
                    $visit->forceFill(['status' => Visit::STATUS_AWAITING_DOCTOR])->save();
                }
                $cancelled++;
            });
        }

        $this->info("Dropped {$droppedMisplaced} misplaced (wrong-clinic) stock rows.");
        $this->info("Topped up {$toppedUp} branch/item stock rows to {$topup} (floor {$floor}).");
        $this->info("Cancelled {$cancelled} orphaned (package-removed) pending stock requests.");

        return self::SUCCESS;
    }
}
