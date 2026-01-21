<?php

namespace App\Services\Clinic;

use App\Models\ClinicItem;
use App\Models\Visit;
use App\Models\VisitItem;
use App\Models\VisitStockRequest;
use App\Models\VisitStockRequestLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitStockRequestService
{
    public function enabled(): bool
    {
        return (bool) config('clinic.stock_requests_enabled', true);
    }

    /**
     * Create or update the active (pending) stock request for a visit.
     *
     * $requirements:
     * [
     *   ['clinic_item_id' => 1, 'qty_base' => 2.0],
     *   ['clinic_item_id' => 2, 'qty_base' => 1.5],
     * ]
     */
    public function createForVisit(
        Visit $visit,
        array $requirements,
        int $requestedByUserId = 0,
        ?string $notes = null,
        bool $setVisitAwaitingStock = true,
    ): ?VisitStockRequest {
        if (! $this->enabled()) {
            return null;
        }

        if ((int) $visit->id <= 0) {
            return null;
        }

        $normalized = $this->normalizeRequirements($requirements);
        if ($normalized->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($visit, $normalized, $requestedByUserId, $notes, $setVisitAwaitingStock) {
            /** @var Visit $freshVisit */
            $freshVisit = Visit::query()->lockForUpdate()->findOrFail((int) $visit->id);

            /** @var VisitStockRequest|null $req */
            $req = VisitStockRequest::query()
                ->where('visit_id', (int) $freshVisit->id)
                ->where('status', VisitStockRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (! $req) {
                $req = new VisitStockRequest;
                $req->visit_id = (int) $freshVisit->id;
                $req->branch_id = (int) $freshVisit->branch_id;
                $req->requested_by_user_id = $requestedByUserId > 0 ? $requestedByUserId : null;
                $req->status = VisitStockRequest::STATUS_PENDING;
                $req->notes = $notes;
                $req->save();
            } else {
                if ($requestedByUserId > 0 && ! $req->requested_by_user_id) {
                    $req->requested_by_user_id = $requestedByUserId;
                }

                if ($notes !== null) {
                    $req->notes = $notes;
                }

                $req->save();
            }

            foreach ($normalized as $line) {
                VisitStockRequestLine::query()->updateOrCreate(
                    [
                        'visit_stock_request_id' => (int) $req->id,
                        'clinic_item_id' => (int) $line['clinic_item_id'],
                    ],
                    [
                        'qty_base' => (float) $line['qty_base'],
                    ]
                );
            }

            $keepItemIds = $normalized->pluck('clinic_item_id')->map(fn ($v) => (int) $v)->all();

            VisitStockRequestLine::query()
                ->where('visit_stock_request_id', (int) $req->id)
                ->whereNotIn('clinic_item_id', $keepItemIds)
                ->delete();

            if ($setVisitAwaitingStock) {
                if (! in_array(($freshVisit->status ?? null), ['completed', 'cancelled', 'no_show'], true)) {
                    $freshVisit->status = 'awaiting_stock';
                    $freshVisit->save();
                }
            }

            return $req->load('lines.clinicItem');
        });
    }

    /**
     * Fulfill request:
     * - consume stock
     * - upsert visit_items (unique: visit_id + clinic_item_id)
     * - mark request fulfilled
     * - resume visit status
     */
    public function fulfill(
        VisitStockRequest $request,
        int $fulfilledByUserId = 0,
        ?string $notes = null,
        string $resumeStatus = 'awaiting_doctor', // or 'in_progress'
    ): VisitStockRequest {
        if (! $this->enabled()) {
            return $request;
        }

        return DB::transaction(function () use ($request, $fulfilledByUserId, $notes, $resumeStatus) {
            /** @var VisitStockRequest $req */
            $req = VisitStockRequest::query()
                ->lockForUpdate()
                ->with(['lines.clinicItem'])
                ->findOrFail((int) $request->id);

            if (($req->status ?? null) !== VisitStockRequest::STATUS_PENDING) {
                return $req; // idempotent
            }

            /** @var Visit $visit */
            $visit = Visit::query()->lockForUpdate()->findOrFail((int) $req->visit_id);

            $branchId = (int) $req->branch_id;

            $stockSvc = app(ClinicStockService::class);

            foreach ($req->lines as $line) {
                $qtyBase = (float) ($line->qty_base ?? 0);
                if ($qtyBase <= 0) {
                    continue;
                }

                /** @var ClinicItem|null $item */
                $item = $line->clinicItem;
                if (! $item) {
                    throw new \RuntimeException('Clinic item not found for stock request line.');
                }

                // Consume (movement includes related morph -> request)
                $stockSvc->consume(
                    $branchId,
                    $item,
                    $qtyBase,
                    $fulfilledByUserId,
                    $notes ? ('Fulfill stock request: '.$notes) : 'Fulfill stock request',
                    $req
                );

                // Upsert visit_items row (unique: visit_id+clinic_item_id)
                $this->upsertVisitItemFromFulfillment($visit, $item, $qtyBase);
            }

            $now = Carbon::now(config('app.timezone', 'Asia/Kuwait'));

            $req->status = VisitStockRequest::STATUS_FULFILLED;
            $req->fulfilled_by_user_id = $fulfilledByUserId > 0 ? $fulfilledByUserId : null;
            $req->fulfilled_at = $now;

            if ($notes !== null) {
                $req->notes = $this->appendNote((string) ($req->notes ?? ''), $notes);
            }

            $req->save();

            if (! in_array(($visit->status ?? null), ['completed', 'cancelled', 'no_show'], true)) {
                $target = in_array($resumeStatus, ['awaiting_doctor', 'in_progress'], true)
                    ? $resumeStatus
                    : 'awaiting_doctor';

                $visit->status = $target;
                $visit->save();
            }

            return $req->refresh()->load('lines.clinicItem');
        });
    }

    public function cancel(VisitStockRequest $request, int $userId = 0, ?string $reason = null): VisitStockRequest
    {
        if (! $this->enabled()) {
            return $request;
        }

        return DB::transaction(function () use ($request, $reason) {
            /** @var VisitStockRequest $req */
            $req = VisitStockRequest::query()->lockForUpdate()->findOrFail((int) $request->id);

            if (($req->status ?? null) !== VisitStockRequest::STATUS_PENDING) {
                return $req;
            }

            $req->status = VisitStockRequest::STATUS_CANCELLED;

            if ($reason !== null) {
                $req->notes = $this->appendNote((string) ($req->notes ?? ''), 'Cancelled: '.$reason);
            }

            $req->save();

            $visit = Visit::query()->lockForUpdate()->find((int) $req->visit_id);
            if ($visit && ($visit->status ?? null) === 'awaiting_stock') {
                $visit->status = 'awaiting_doctor';
                $visit->save();
            }

            return $req;
        });
    }

    /**
     * Your visit_items has UNIQUE(visit_id, clinic_item_id).
     * We therefore accumulate qty and recompute totals.
     */
    protected function upsertVisitItemFromFulfillment(Visit $visit, ClinicItem $item, float $qtyToAdd): void
    {
        $visitId = (int) $visit->id;
        $itemId = (int) $item->id;

        if ($visitId <= 0 || $itemId <= 0 || $qtyToAdd <= 0) {
            return;
        }

        /** @var VisitItem $vi */
        $vi = VisitItem::query()
            ->where('visit_id', $visitId)
            ->where('clinic_item_id', $itemId)
            ->lockForUpdate()
            ->first();

        if (! $vi) {
            $vi = new VisitItem;
            $vi->visit_id = $visitId;
            $vi->clinic_item_id = $itemId;
            $vi->branch_id = (int) ($visit->branch_id ?? null);

            // Snapshots from current defaults (first write wins)
            $vi->unit_cost_snapshot = (float) ($item->default_cost ?? 0);
            $vi->unit_price_snapshot = (float) ($item->default_price ?? 0);

            $vi->qty = 0;
            $vi->line_cost_total = 0;
            $vi->line_price_total = 0;
        } else {
            // Ensure branch_id is set (nullable column)
            if (! $vi->branch_id) {
                $vi->branch_id = (int) ($visit->branch_id ?? null);
            }

            // Preserve existing snapshots (audit stability)
            if ((float) ($vi->unit_cost_snapshot ?? 0) <= 0) {
                $vi->unit_cost_snapshot = (float) ($item->default_cost ?? 0);
            }
            if ((float) ($vi->unit_price_snapshot ?? 0) <= 0) {
                $vi->unit_price_snapshot = (float) ($item->default_price ?? 0);
            }
        }

        $currentQty = (float) ($vi->qty ?? 0);
        $newQty = $currentQty + $qtyToAdd;

        $unitCost = (float) ($vi->unit_cost_snapshot ?? 0);
        $unitPrice = (float) ($vi->unit_price_snapshot ?? 0);

        // qty is decimal(12,3) in DB; this is fine (DB will round).
        $vi->qty = $newQty;

        $vi->line_cost_total = $newQty * $unitCost;
        $vi->line_price_total = $newQty * $unitPrice;

        $vi->save();
    }

    protected function normalizeRequirements(array $requirements): Collection
    {
        return collect($requirements)
            ->map(fn ($r) => [
                'clinic_item_id' => (int) ($r['clinic_item_id'] ?? 0),
                'qty_base' => (float) ($r['qty_base'] ?? 0),
            ])
            ->filter(fn ($r) => $r['clinic_item_id'] > 0 && $r['qty_base'] > 0)
            ->groupBy('clinic_item_id')
            ->map(fn ($rows, $itemId) => [
                'clinic_item_id' => (int) $itemId,
                'qty_base' => (float) $rows->sum(fn ($x) => (float) $x['qty_base']),
            ])
            ->values();
    }

    protected function appendNote(string $existing, string $add): string
    {
        $existing = trim($existing);
        $add = trim($add);

        if ($add === '') {
            return $existing;
        }
        if ($existing === '') {
            return $add;
        }

        return $existing."\n---\n".$add;
    }
}
