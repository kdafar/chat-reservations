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
use Illuminate\Support\Facades\Log;

class VisitStockRequestService
{
    public function enabled(): bool
    {
        return (bool) config('clinic.stock_requests_enabled', true);
    }

    /**
     * Create or update the active (pending) stock request for a visit.
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

        // [Legacy Architect] This now filters out Services automatically
        $normalized = $this->normalizeRequirements($requirements);

        if ($normalized->isEmpty()) {
            return null; // Nothing stockable to request
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

            // Snapshot the items' current cost/price into each line at request
            // time. If the admin changes item prices between request and
            // fulfillment, the patient's invoice will still reflect what was
            // agreed at request time.
            $itemIds = $normalized->pluck('clinic_item_id')->map(fn ($v) => (int) $v)->all();
            $itemsMap = ClinicItem::query()
                ->whereIn('id', $itemIds)
                ->get(['id', 'default_cost', 'default_price'])
                ->keyBy('id');

            // MERGE semantics: each call adds to the pending request. Doctor
            // adds package A, then B before fulfilling A — both go into the
            // same pending request, both get consumed at "Stock Arrived".
            //
            // Previously this method REPLACED existing lines and DELETED any
            // not in the new requirements set — which silently lost package
            // A's items when package B was added afterwards.
            foreach ($normalized as $line) {
                $itemId = (int) $line['clinic_item_id'];
                $item = $itemsMap->get($itemId);
                $addQty = (float) $line['qty_base'];

                $existing = VisitStockRequestLine::query()
                    ->where('visit_stock_request_id', (int) $req->id)
                    ->where('clinic_item_id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    // Increment qty; keep the original price snapshot — we
                    // already promised that price to the patient.
                    $existing->forceFill([
                        'qty_base' => (float) $existing->qty_base + $addQty,
                    ])->save();
                } else {
                    VisitStockRequestLine::create([
                        'visit_stock_request_id' => (int) $req->id,
                        'clinic_item_id' => $itemId,
                        'qty_base' => $addQty,
                        'unit_cost_snapshot' => (float) ($item->default_cost ?? 0),
                        'unit_price_snapshot' => (float) ($item->default_price ?? 0),
                    ]);
                }
            }

            // NO destructive delete of lines outside the new requirements set.
            // That logic was removed because it corrupted multi-package flows.

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
     * Fulfill request: consume stock (only if stockable) and upsert visit_items
     */
    public function fulfill(
        VisitStockRequest $request,
        int $fulfilledByUserId = 0,
        ?string $notes = null,
        string $resumeStatus = 'awaiting_doctor',
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
                return $req;
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
                    // Log warning but don't crash entire batch if item deleted
                    Log::warning("Skipping missing item in request #{$req->id}", ['line_id' => $line->id]);

                    continue;
                }

                // [Legacy Architect Fix]
                // If item is NOT stockable (e.g. Service), do NOT try to consume stock.
                // Just add it to the visit bill and continue.
                if ($item->is_stockable) {
                    $stockSvc->consume(
                        $branchId,
                        $item,
                        $qtyBase,
                        $fulfilledByUserId,
                        $notes ? ('Fulfill stock request: '.$notes) : 'Fulfill stock request',
                        $req
                    );
                }

                // Always add to visit items (Bill/Usage record).
                // Use the line's price snapshot when present so admin price changes
                // between request and fulfillment don't bleed into the patient bill.
                $this->upsertVisitItemFromFulfillment(
                    $visit,
                    $item,
                    $qtyBase,
                    $line->unit_cost_snapshot !== null ? (float) $line->unit_cost_snapshot : null,
                    $line->unit_price_snapshot !== null ? (float) $line->unit_price_snapshot : null,
                );
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
     * Upsert a VisitItem from a fulfillment event.
     *
     * The caller may pass an explicit cost/price snapshot — used when fulfilling
     * a VisitStockRequest, so prices locked at request time are honored.
     * When the overrides are null we fall back to the item's current default_*.
     */
    protected function upsertVisitItemFromFulfillment(
        Visit $visit,
        ClinicItem $item,
        float $qtyToAdd,
        ?float $unitCostOverride = null,
        ?float $unitPriceOverride = null,
    ): void {
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
            $vi->unit_cost_snapshot = (float) ($unitCostOverride ?? $item->default_cost ?? 0);
            $vi->unit_price_snapshot = (float) ($unitPriceOverride ?? $item->default_price ?? 0);
            $vi->qty = 0;
            $vi->line_cost_total = 0;
            $vi->line_price_total = 0;
        } else {
            if (! $vi->branch_id) {
                $vi->branch_id = (int) ($visit->branch_id ?? null);
            }
        }

        $currentQty = (float) ($vi->qty ?? 0);
        $newQty = $currentQty + $qtyToAdd;
        $unitCost = (float) ($vi->unit_cost_snapshot ?? 0);
        $unitPrice = (float) ($vi->unit_price_snapshot ?? 0);

        $vi->qty = $newQty;
        $vi->line_cost_total = $newQty * $unitCost;
        $vi->line_price_total = $newQty * $unitPrice;
        $vi->save();
    }

    /**
     * [Legacy Architect Fix]
     * Normalize requirements AND filter out non-stockable items (Services).
     * This prevents services from triggering stock alerts.
     */
    protected function normalizeRequirements(array $requirements): Collection
    {
        // 1. Initial cleanup
        $rows = collect($requirements)
            ->map(fn ($r) => [
                'clinic_item_id' => (int) ($r['clinic_item_id'] ?? 0),
                'qty_base' => (float) ($r['qty_base'] ?? 0),
            ])
            ->filter(fn ($r) => $r['clinic_item_id'] > 0 && $r['qty_base'] > 0);

        if ($rows->isEmpty()) {
            return collect();
        }

        // 2. Database Filter: Only keep IDs where is_stockable = 1
        $requestedIds = $rows->pluck('clinic_item_id')->unique()->values()->all();

        $stockableIds = \App\Models\ClinicItem::query()
            ->whereIn('id', $requestedIds)
            ->where('is_stockable', true) // <--- THIS IS THE FIX
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // 3. Filter the rows to match valid stockable IDs
        return $rows
            ->filter(fn ($r) => in_array($r['clinic_item_id'], $stockableIds))
            ->groupBy('clinic_item_id')
            ->map(fn ($group, $itemId) => [
                'clinic_item_id' => (int) $itemId,
                'qty_base' => (float) $group->sum('qty_base'),
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

    /**
     * One-shot orchestrator: try to issue stock immediately; otherwise create
     * a pending VisitStockRequest. Always exactly one stock-affecting action,
     * so callers can't accidentally double-consume.
     *
     * @return array{mode: string, request_id: int}
     */
    public function issueOrRequestForVisit(
        Visit $visit,
        array $requirements,
        int $userId = 0,
        ?string $notes = null,
        ?string $trace = null,
    ): array {
        if (! $this->enabled()) {
            return ['mode' => 'disabled', 'request_id' => 0];
        }

        // Keep the visit's current operating status when issuing directly so
        // we don't bump an awaiting_doctor visit into in_progress prematurely.
        $keepStatus = in_array(($visit->status ?? null), ['awaiting_doctor', 'in_progress'], true)
            ? (string) $visit->status
            : 'awaiting_doctor';

        $issued = $this->issueDirectlyIfInStock(
            $visit,
            $requirements,
            $userId,
            $notes,
            $keepStatus,
            $trace
        );

        if ($issued) {
            return ['mode' => 'issued', 'request_id' => 0];
        }

        $req = $this->createForVisit($visit, $requirements, $userId, $notes, true);

        return ['mode' => 'request', 'request_id' => (int) ($req?->id ?? 0)];
    }

    public function issueDirectlyIfInStock(
        Visit $visit,
        array $requirements,
        int $userId = 0,
        ?string $notes = null,
        string $keepVisitStatus = 'in_progress',
        ?string $trace = null,
    ): bool {
        if (! $this->enabled()) {
            return false;
        }

        $visitId = (int) $visit->id;
        if ($visitId <= 0) {
            return false;
        }

        $trace = $trace ?: 'ISSUE-'.now()->format('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6);

        // This will now return EMPTY if only services are present
        $normalized = $this->normalizeRequirements($requirements);

        if ($normalized->isEmpty()) {
            Log::info('[VisitStockRequestService][issueDirectlyIfInStock] empty requirements (all services?)', [
                'trace' => $trace,
                'visit_id' => $visitId,
            ]);

            // If nothing needs stock, we return TRUE so the flow continues (Green).
            return true;
        }

        return DB::transaction(function () use ($visitId, $normalized, $userId, $notes, $keepVisitStatus, $trace) {
            /** @var Visit $freshVisit */
            $freshVisit = Visit::query()->lockForUpdate()->findOrFail($visitId);

            $branchId = (int) ($freshVisit->branch_id ?? 0);
            $stockSvc = app(ClinicStockService::class);

            $shortages = $stockSvc->shortagesForRequirements($branchId, $normalized->values()->all());

            Log::info('[VisitStockRequestService][issueDirectlyIfInStock] check', [
                'trace' => $trace,
                'visit_id' => $visitId,
                'req_count' => $normalized->count(),
                'shortages_count' => count($shortages),
            ]);

            if (! empty($shortages)) {
                return false;
            }

            foreach ($normalized as $line) {
                $itemId = (int) $line['clinic_item_id'];
                $qtyBase = (float) $line['qty_base'];

                /** @var ClinicItem $item */
                $item = ClinicItem::query()->findOrFail($itemId);

                $stockSvc->consume(
                    $branchId,
                    $item,
                    $qtyBase,
                    $userId,
                    $notes ? ('Direct issue: '.$notes) : 'Direct issue',
                    null
                );

                $this->upsertVisitItemFromFulfillment($freshVisit, $item, $qtyBase);
            }

            if (! in_array(($freshVisit->status ?? null), ['completed', 'cancelled', 'no_show'], true)) {
                $freshVisit->status = $keepVisitStatus;
                $freshVisit->save();
            }

            return true;
        });
    }
}
