<?php

namespace App\Services\Clinic;

use App\Models\Accounting\AccountingPeriod;
use App\Models\ClinicItem;
use App\Models\ClinicStockMovement;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderLine;
use App\Models\Purchasing\PurchasePayment;
use App\Models\Purchasing\PurchaseReceipt;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * International Purchase-to-Pay orchestration. A PO is raised to a vendor in the
 * vendor's currency, runs a submit → approve → send → acknowledge lifecycle,
 * then goods are received into a branch's stock (partial allowed) and the vendor
 * is paid.
 *
 * Money: line unit costs are in the PO `currency`; `exchange_rate` (KWD per 1
 * unit) converts to KWD for the books. Landed costs (freight/customs/clearance/
 * insurance/other, all KWD) capitalise into inventory on receipt, allocated
 * proportionally to the goods value received.
 *
 * Receiving restocks via a GL-neutral 'purchase_in' movement, then posts
 * Dr Inventory / Cr Accounts Payable (goods) / Cr Import Costs Payable (landed).
 * Paying posts Dr Accounts Payable / Cr Cash/Bank. All GL writes go through
 * AccountingService (idempotent, never break the operational flow).
 */
class PurchaseService
{
    public function __construct(
        protected ClinicStockService $stock,
        protected AccountingService $accounting,
    ) {}

    /** Create a draft PO. $lines = [['clinic_item_id','qty_ordered','unit_cost','country_of_origin'?], ...]. */
    public function create(int $vendorId, int $branchId, array $lines, array $attrs = [], int $userId = 0): PurchaseOrder
    {
        $clean = $this->cleanLines($lines);
        if ($clean->isEmpty()) {
            throw new \RuntimeException('Add at least one item to the purchase order.');
        }

        return DB::transaction(function () use ($vendorId, $branchId, $clean, $attrs, $userId) {
            $orderDate = $attrs['order_date'] ?? now()->toDateString();
            $po = PurchaseOrder::create(array_merge(
                $this->headerAttrs($attrs),
                [
                    'code' => PurchaseOrder::generateCode($orderDate),
                    'vendor_id' => $vendorId,
                    'branch_id' => $branchId,
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'order_date' => $orderDate,
                    'created_by_user_id' => $userId ?: null,
                ],
            ));
            $this->syncLines($po, $clean);

            return $po->refresh();
        });
    }

    /** Replace the lines / header of a draft (or rejected) PO. */
    public function update(PurchaseOrder $po, array $lines, array $attrs = []): PurchaseOrder
    {
        if (! $po->isEditable()) {
            throw new \RuntimeException('Only a draft purchase order can be edited.');
        }
        $clean = $this->cleanLines($lines);
        if ($clean->isEmpty()) {
            throw new \RuntimeException('Add at least one item to the purchase order.');
        }

        return DB::transaction(function () use ($po, $clean, $attrs) {
            // Editing a rejected PO returns it to draft.
            $po->forceFill(array_merge($this->headerAttrs($attrs, $po), [
                'status' => PurchaseOrder::STATUS_DRAFT,
                'rejected_at' => null, 'rejected_by_user_id' => null, 'rejection_reason' => null,
            ]))->save();
            $po->lines()->delete();
            $this->syncLines($po, $clean);

            return $po->refresh();
        });
    }

    /** draft → pending_approval. */
    public function submit(PurchaseOrder $po, int $userId = 0): PurchaseOrder
    {
        if (! in_array($po->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_REJECTED], true)) {
            throw new \RuntimeException('Only a draft purchase order can be submitted for approval.');
        }
        if ($po->lines()->count() === 0) {
            throw new \RuntimeException('Cannot submit an empty purchase order.');
        }
        $po->forceFill([
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'submitted_by_user_id' => $userId ?: null,
            'submitted_at' => now(),
        ])->save();

        return $po;
    }

    /** pending_approval → approved. */
    public function approve(PurchaseOrder $po, int $userId = 0): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw new \RuntimeException('Only a purchase order pending approval can be approved.');
        }
        $po->forceFill([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by_user_id' => $userId ?: null,
            'approved_at' => now(),
        ])->save();

        return $po;
    }

    /** pending_approval → rejected (with reason). */
    public function reject(PurchaseOrder $po, ?string $reason, int $userId = 0): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw new \RuntimeException('Only a purchase order pending approval can be rejected.');
        }
        $po->forceFill([
            'status' => PurchaseOrder::STATUS_REJECTED,
            'rejected_by_user_id' => $userId ?: null,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $po;
    }

    /** approved → sent (PO issued to the vendor). */
    public function send(PurchaseOrder $po, int $userId = 0): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_APPROVED) {
            throw new \RuntimeException('Only an approved purchase order can be sent to the vendor.');
        }
        $po->forceFill([
            'status' => PurchaseOrder::STATUS_SENT,
            'sent_by_user_id' => $userId ?: null,
            'sent_at' => now(),
        ])->save();

        return $po;
    }

    /** sent → acknowledged (vendor confirmed the order). */
    public function acknowledge(PurchaseOrder $po, ?string $vendorReference = null): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_SENT) {
            throw new \RuntimeException('Only a sent purchase order can be acknowledged.');
        }
        $po->forceFill([
            'status' => PurchaseOrder::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'vendor_reference' => $vendorReference ?: $po->vendor_reference,
        ])->save();

        return $po;
    }

    /** received → closed. */
    public function close(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_RECEIVED) {
            throw new \RuntimeException('Only a fully received purchase order can be closed.');
        }
        $po->forceFill(['status' => PurchaseOrder::STATUS_CLOSED, 'closed_at' => now()])->save();

        return $po;
    }

    /**
     * Receive goods against a PO. $lines = [['purchase_order_line_id','qty'], ...].
     * Restocks each item (GL-neutral 'purchase_in') and posts one entry:
     * Dr Inventory (goods KWD + allocated landed) / Cr AP (goods) / Cr Import Costs Payable (landed).
     */
    public function receive(PurchaseOrder $po, array $lines, int $userId = 0, ?string $notes = null): PurchaseReceipt
    {
        if (! $po->isReceivable()) {
            throw new \RuntimeException('This purchase order is not in a receivable state (send it to the vendor first).');
        }
        $this->assertPeriodOpen(now());

        $po->loadMissing('lines');
        $byId = $po->lines->keyBy('id');

        $toReceive = collect($lines)
            ->map(fn ($l) => [
                'line_id' => (int) ($l['purchase_order_line_id'] ?? 0),
                'qty' => (float) ($l['qty'] ?? 0),
            ])
            ->filter(fn ($l) => $l['line_id'] > 0 && $l['qty'] > 0)
            ->values();

        if ($toReceive->isEmpty()) {
            throw new \RuntimeException('Enter at least one quantity to receive.');
        }

        foreach ($toReceive as $r) {
            $line = $byId->get($r['line_id']);
            if (! $line) {
                throw new \RuntimeException('Unknown order line in this purchase order.');
            }
            if ($r['qty'] > $line->qtyRemaining() + 0.0001) {
                throw new \RuntimeException("Cannot receive more than the outstanding quantity for {$line->clinicItem?->localized_name}.");
            }
        }

        $rate = (float) ($po->exchange_rate ?: 1);
        $poGoodsForeign = (float) $po->goods_total;
        $landedTotal = (float) $po->landed_total;
        $priorLanded = $po->landedCapitalised();

        return DB::transaction(function () use ($po, $toReceive, $byId, $userId, $notes, $rate, $poGoodsForeign, $landedTotal, $priorLanded) {
            $receipt = PurchaseReceipt::create([
                'code' => PurchaseReceipt::generateCode(now()),
                'purchase_order_id' => $po->id,
                'branch_id' => $po->branch_id,
                'received_by_user_id' => $userId ?: null,
                'received_at' => now(),
                'total_amount' => 0,
                'landed_amount' => 0,
                'notes' => $notes,
            ]);

            $goodsForeign = 0.0;
            foreach ($toReceive as $r) {
                $line = $byId->get($r['line_id']);
                $item = ClinicItem::query()->withoutGlobalScopes()->find($line->clinic_item_id);
                if (! $item) {
                    throw new \RuntimeException('Item no longer exists.');
                }
                $qty = (float) $r['qty'];
                $unitCost = $line->netUnitCost(); // PO currency, after line discount
                $lineTotal = round($qty * $unitCost, 3);

                // 'purchase_in' is ignored by the accounting observer — this
                // service posts the GL entry itself at the landed KWD cost.
                $stockRow = $this->stock->restock(
                    (int) $po->branch_id,
                    $item,
                    $qty,
                    null,
                    $userId,
                    "Purchase {$po->code} — receipt {$receipt->code}",
                    $receipt,
                    'purchase_in',
                );
                $movementId = $stockRow->movements()->latest('id')->value('id');

                $receipt->lines()->create([
                    'purchase_order_line_id' => $line->id,
                    'clinic_item_id' => $line->clinic_item_id,
                    'qty_received' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'clinic_stock_movement_id' => $movementId,
                ]);

                $line->forceFill(['qty_received' => (float) $line->qty_received + $qty])->save();
                $goodsForeign += $lineTotal;
            }

            $goodsKwd = round($goodsForeign * $rate, 3);

            // Decide the new status, then allocate landed cost. On the receipt
            // that completes the PO, true-up so total landed exactly capitalises.
            $po->load('lines');
            $fullyReceived = $po->lines->every(fn ($l) => $l->qtyRemaining() <= 0.0001);

            $landed = 0.0;
            if ($landedTotal > 0) {
                $landed = $fullyReceived
                    ? round($landedTotal - $priorLanded, 3)
                    : ($poGoodsForeign > 0 ? round($landedTotal * ($goodsForeign / $poGoodsForeign), 3) : 0.0);
                $landed = max(0.0, $landed);
            }

            $receipt->forceFill(['total_amount' => $goodsKwd, 'landed_amount' => $landed])->save();

            $this->accounting->recordPurchaseReceipt($receipt->refresh(), $userId ?: null);

            $attrs = [
                'status' => $fullyReceived
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ];
            // First goods received → start the payment clock (net terms).
            if (! $po->payment_due_date) {
                $attrs['payment_due_date'] = now()->addDays((int) $po->payment_terms_days)->toDateString();
            }
            $po->forceFill($attrs)->save();

            return $receipt->refresh();
        });
    }

    /** Record a vendor payment (KWD) against the PO. Dr AP / Cr Cash/Bank. */
    public function pay(PurchaseOrder $po, array $data, int $userId = 0): PurchasePayment
    {
        $amount = round((float) ($data['amount'] ?? 0), 3);
        if ($amount <= 0) {
            throw new \RuntimeException('Enter a payment amount greater than zero.');
        }
        $outstanding = $po->outstanding();
        if ($amount > $outstanding + 0.0001) {
            throw new \RuntimeException('Payment exceeds the outstanding balance ('.number_format($outstanding, 3).' KWD).');
        }
        $this->assertPeriodOpen($data['payment_date'] ?? now());

        return DB::transaction(function () use ($po, $data, $amount, $userId) {
            $date = $data['payment_date'] ?? now()->toDateString();
            $payment = PurchasePayment::create([
                'code' => PurchasePayment::generateCode($date),
                'purchase_order_id' => $po->id,
                'branch_id' => $po->branch_id,
                'vendor_id' => $po->vendor_id,
                'amount' => $amount,
                'payment_date' => $date,
                'method' => $data['method'] ?? 'cash',
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_by_user_id' => $userId ?: null,
            ]);

            $this->accounting->recordPurchasePayment($payment->refresh(), $userId ?: null);

            return $payment->refresh();
        });
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        if (! $po->isCancellable()) {
            throw new \RuntimeException('This purchase order can no longer be cancelled.');
        }
        $po->forceFill(['status' => PurchaseOrder::STATUS_CANCELLED])->save();

        return $po;
    }

    public function voidPayment(PurchasePayment $payment): PurchasePayment
    {
        return DB::transaction(function () use ($payment) {
            $this->accounting->recordPurchasePaymentReversal($payment, 'Vendor payment voided');
            $payment->delete();

            return $payment;
        });
    }

    /**
     * Reverse a goods receipt: consume the received stock back out, reverse the
     * GL entry, undo the received quantities, and flag the receipt. Throws (and
     * rolls back) if that stock has since been consumed/sold and can't be pulled.
     */
    public function reverseReceipt(PurchaseReceipt $receipt, int $userId = 0): PurchaseReceipt
    {
        if ($receipt->reversed_at) {
            throw new \RuntimeException('This receipt has already been reversed.');
        }
        $this->assertPeriodOpen(now());

        return DB::transaction(function () use ($receipt, $userId) {
            $receipt->loadMissing(['lines', 'purchaseOrder']);
            $po = $receipt->purchaseOrder;

            foreach ($receipt->lines as $rl) {
                $item = ClinicItem::query()->withoutGlobalScopes()->find($rl->clinic_item_id);
                if (! $item) {
                    continue;
                }
                // Pull back exactly the base quantity the receipt added.
                $movement = $rl->clinic_stock_movement_id
                    ? ClinicStockMovement::query()->withoutGlobalScopes()->find($rl->clinic_stock_movement_id)
                    : null;
                $qtyBase = $movement ? abs((float) $movement->qty_change_base) : null;

                // consume() throws if the branch is now short → whole reversal rolls back.
                $this->stock->consume(
                    (int) $receipt->branch_id,
                    $item,
                    $qtyBase ?? (float) $rl->qty_received,
                    $userId,
                    "Reversal of receipt {$receipt->code}",
                    $receipt,
                    'purchase_in_reversal',
                );

                // Undo the received quantity on the PO line.
                $line = PurchaseOrderLine::query()->find($rl->purchase_order_line_id);
                if ($line) {
                    $line->forceFill(['qty_received' => max(0, (float) $line->qty_received - (float) $rl->qty_received)])->save();
                }
            }

            // Reverse the GL entry and flag the receipt.
            $this->accounting->recordPurchaseReceiptReversal($receipt, "Receipt {$receipt->code} reversed");
            $receipt->forceFill(['reversed_at' => now(), 'reversed_by_user_id' => $userId ?: null])->save();

            // Recompute PO status from what's left received.
            if ($po) {
                $po->load('lines');
                $anyReceived = $po->lines->sum(fn ($l) => (float) $l->qty_received) > 0.0001;
                $po->forceFill(['status' => $anyReceived
                    ? PurchaseOrder::STATUS_PARTIALLY_RECEIVED
                    : ($po->acknowledged_at ? PurchaseOrder::STATUS_ACKNOWLEDGED
                        : ($po->sent_at ? PurchaseOrder::STATUS_SENT : PurchaseOrder::STATUS_APPROVED)),
                ])->save();
            }

            return $receipt->refresh();
        });
    }

    /**
     * Short-close a partially-received PO the vendor will not complete. Any
     * landed cost not yet capitalised is posted to inventory (so Import Costs
     * Payable reconciles to the freight bill), then the PO is closed.
     */
    public function shortClose(PurchaseOrder $po, int $userId = 0): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_PARTIALLY_RECEIVED) {
            throw new \RuntimeException('Only a partially-received purchase order can be short-closed.');
        }
        $this->assertPeriodOpen(now());

        return DB::transaction(function () use ($po, $userId) {
            $remaining = round((float) $po->landed_total - $po->landedCapitalised(), 3);
            if ($remaining > 0.001) {
                $receipt = PurchaseReceipt::create([
                    'code' => PurchaseReceipt::generateCode(now()),
                    'purchase_order_id' => $po->id,
                    'branch_id' => $po->branch_id,
                    'received_by_user_id' => $userId ?: null,
                    'received_at' => now(),
                    'total_amount' => 0,           // no goods — landed-cost true-up only
                    'landed_amount' => $remaining,
                    'notes' => 'Landed-cost true-up on short close',
                ]);
                $this->accounting->recordPurchaseReceipt($receipt->refresh(), $userId ?: null);
            }

            $po->forceFill(['status' => PurchaseOrder::STATUS_CLOSED, 'closed_at' => now()])->save();

            return $po;
        });
    }

    /** Block posting into a closed accounting period before any stock/GL change. */
    private function assertPeriodOpen(Carbon|string $date): void
    {
        $period = AccountingPeriod::forDate($date instanceof Carbon ? $date : Carbon::parse($date));
        if ($period->isClosed()) {
            throw new \RuntimeException("The accounting period {$period->code} is closed — reopen it before posting purchases dated within it.");
        }
    }

    /** Normalise header attributes (currency / fx / incoterm / landed / shipment). */
    private function headerAttrs(array $a, ?PurchaseOrder $existing = null): array
    {
        $currency = strtoupper((string) ($a['currency'] ?? $existing?->currency ?? 'KWD'));
        $rate = $currency === 'KWD' ? 1.0 : (float) ($a['exchange_rate'] ?? $existing?->exchange_rate ?? 1);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        return [
            'currency' => $currency,
            'exchange_rate' => $rate,
            'incoterm' => $a['incoterm'] ?? $existing?->incoterm,
            'payment_terms_days' => (int) ($a['payment_terms_days'] ?? $existing?->payment_terms_days ?? 0),
            'expected_date' => $a['expected_date'] ?? $existing?->expected_date,
            'notes' => $a['notes'] ?? $existing?->notes,
            'vendor_reference' => $a['vendor_reference'] ?? $existing?->vendor_reference,
            'carrier' => $a['carrier'] ?? $existing?->carrier,
            'tracking_no' => $a['tracking_no'] ?? $existing?->tracking_no,
            'container_no' => $a['container_no'] ?? $existing?->container_no,
            'ship_date' => $a['ship_date'] ?? $existing?->ship_date,
            'eta' => $a['eta'] ?? $existing?->eta,
            'freight_amount' => round((float) ($a['freight_amount'] ?? $existing?->freight_amount ?? 0), 3),
            'customs_amount' => round((float) ($a['customs_amount'] ?? $existing?->customs_amount ?? 0), 3),
            'clearance_amount' => round((float) ($a['clearance_amount'] ?? $existing?->clearance_amount ?? 0), 3),
            'insurance_amount' => round((float) ($a['insurance_amount'] ?? $existing?->insurance_amount ?? 0), 3),
            'other_charges_amount' => round((float) ($a['other_charges_amount'] ?? $existing?->other_charges_amount ?? 0), 3),
        ];
    }

    /** Normalise + validate incoming line rows. */
    private function cleanLines(array $lines): Collection
    {
        return collect($lines)
            ->map(fn ($l) => [
                'clinic_item_id' => (int) ($l['clinic_item_id'] ?? 0),
                'qty_ordered' => (float) ($l['qty_ordered'] ?? 0),
                'unit_cost' => round((float) ($l['unit_cost'] ?? 0), 3),
                'discount_type' => ($l['discount_type'] ?? 'percent') === 'amount' ? 'amount' : 'percent',
                'discount_value' => max(0, round((float) ($l['discount_value'] ?? 0), 3)),
                'country_of_origin' => $l['country_of_origin'] ?? null,
            ])
            ->filter(fn ($l) => $l['clinic_item_id'] > 0 && $l['qty_ordered'] > 0)
            ->values();
    }

    /** Create lines on a PO and recompute its money totals (foreign + KWD + landed + grand). */
    private function syncLines(PurchaseOrder $po, Collection $clean): void
    {
        $goodsForeign = 0.0;
        foreach ($clean as $l) {
            $gross = $l['qty_ordered'] * $l['unit_cost'];
            $discount = PurchaseOrderLine::computeDiscount($gross, $l['discount_type'], $l['discount_value']);
            $lineTotal = round($gross - $discount, 3);
            $po->lines()->create([
                'clinic_item_id' => $l['clinic_item_id'],
                'country_of_origin' => $l['country_of_origin'],
                'qty_ordered' => $l['qty_ordered'],
                'qty_received' => 0,
                'unit_cost' => $l['unit_cost'],
                'discount_type' => $l['discount_type'],
                'discount_value' => $l['discount_value'],
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
            ]);
            $goodsForeign += $lineTotal;
        }

        $rate = (float) ($po->exchange_rate ?: 1);
        $goodsKwd = round($goodsForeign * $rate, 3);
        $landed = round(
            (float) $po->freight_amount + (float) $po->customs_amount + (float) $po->clearance_amount
            + (float) $po->insurance_amount + (float) $po->other_charges_amount,
            3,
        );

        $po->forceFill([
            'goods_total' => round($goodsForeign, 3),
            'goods_total_kwd' => $goodsKwd,
            'landed_total' => $landed,
            'subtotal' => $goodsKwd,
            'total' => round($goodsKwd + $landed, 3),
        ])->save();
    }
}
