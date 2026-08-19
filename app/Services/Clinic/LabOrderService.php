<?php

namespace App\Services\Clinic;

use App\Models\Lab\LabOrder;
use App\Models\Lab\LabOrderItem;
use App\Models\Lab\LabTest;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCharge;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * The lab workflow, in one place.
 *
 *   doctor:   order(...)                    → status ordered
 *   lab:      collectSample(...)            → status sample_collected
 *   lab:      start(...)                    → status in_progress
 *   lab:      saveResults(...)              → per-test values + auto flags
 *   lab:      complete(...)                 → status completed, doctor notified
 *   doctor:   review(...)                   → loop closed
 *
 * Billing: each ordered test snapshots its catalog price onto a VisitCharge so
 * the test shows up on the patient's bill the moment the doctor orders it (same
 * as a service). Cancelling a test pulls its charge back off the visit — that's
 * what visit_charge_id on the item is for.
 */
class LabOrderService
{
    public function __construct(protected VisitChargeService $charges) {}

    /**
     * Create an order on a visit for the given catalog test ids.
     *
     * @param  array<int>  $testIds
     */
    public function order(
        Visit $visit,
        array $testIds,
        string $priority = LabOrder::PRIORITY_ROUTINE,
        ?string $clinicalNote = null,
        int $userId = 0,
        bool $bill = true,
    ): LabOrder {
        $testIds = array_values(array_unique(array_map('intval', $testIds)));
        if ($testIds === []) {
            throw new \RuntimeException('Pick at least one test.');
        }

        $tests = LabTest::query()
            ->whereIn('id', $testIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($tests->isEmpty()) {
            throw new \RuntimeException('None of the selected tests are available.');
        }

        return DB::transaction(function () use ($visit, $testIds, $tests, $priority, $clinicalNote, $userId, $bill) {
            $order = LabOrder::create([
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'branch_id' => $visit->branch_id,
                'doctor_id' => $visit->doctor_id,
                'status' => LabOrder::STATUS_ORDERED,
                'priority' => $priority === LabOrder::PRIORITY_URGENT
                    ? LabOrder::PRIORITY_URGENT
                    : LabOrder::PRIORITY_ROUTINE,
                'clinical_note' => $clinicalNote ?: null,
                'ordered_at' => now(),
                'ordered_by_user_id' => $userId > 0 ? $userId : null,
            ]);

            foreach ($testIds as $id) {
                /** @var LabTest|null $test */
                $test = $tests->get($id);
                if (! $test) {
                    continue;
                }

                [$low, $high] = $this->parseReferenceRange((string) $test->reference_range);

                $chargeId = null;
                $price = (float) $test->default_price;
                if ($bill && $price > 0) {
                    $charge = $this->charges->addCharge(
                        $visit,
                        $this->chargeLabel($test),
                        1.0,
                        $price,
                        $userId,
                    );
                    $chargeId = (int) $charge->id;
                }

                LabOrderItem::create([
                    'lab_order_id' => $order->id,
                    'lab_test_id' => $test->id,
                    'status' => LabOrderItem::STATUS_PENDING,
                    'result_unit' => $test->unit,
                    'reference_range_snapshot' => $test->reference_range,
                    'ref_low' => $low,
                    'ref_high' => $high,
                    'price_snapshot' => $price,
                    'visit_charge_id' => $chargeId,
                ]);
            }

            return $order->fresh(['items.labTest']);
        });
    }

    /** Add more tests to an order that's still open. */
    public function addTests(LabOrder $order, array $testIds, int $userId = 0, bool $bill = true): LabOrder
    {
        if (! $order->isOpen()) {
            throw new \RuntimeException('This order is closed — create a new order instead.');
        }

        $visit = $order->visit;
        if (! $visit) {
            throw new \RuntimeException('The order has no visit.');
        }

        $existing = $order->items()->pluck('lab_test_id')->all();
        $testIds = array_values(array_diff(array_map('intval', $testIds), $existing));
        if ($testIds === []) {
            return $order->fresh(['items.labTest']);
        }

        $tests = LabTest::query()->whereIn('id', $testIds)->where('is_active', true)->get();

        DB::transaction(function () use ($order, $visit, $tests, $userId, $bill) {
            foreach ($tests as $test) {
                [$low, $high] = $this->parseReferenceRange((string) $test->reference_range);

                $chargeId = null;
                $price = (float) $test->default_price;
                if ($bill && $price > 0) {
                    $chargeId = (int) $this->charges->addCharge(
                        $visit, $this->chargeLabel($test), 1.0, $price, $userId
                    )->id;
                }

                LabOrderItem::create([
                    'lab_order_id' => $order->id,
                    'lab_test_id' => $test->id,
                    'status' => LabOrderItem::STATUS_PENDING,
                    'result_unit' => $test->unit,
                    'reference_range_snapshot' => $test->reference_range,
                    'ref_low' => $low,
                    'ref_high' => $high,
                    'price_snapshot' => $price,
                    'visit_charge_id' => $chargeId,
                ]);
            }
        });

        return $order->fresh(['items.labTest']);
    }

    public function collectSample(LabOrder $order, int $userId = 0): LabOrder
    {
        $this->assertOpen($order);

        $order->forceFill([
            'status' => LabOrder::STATUS_SAMPLE_COLLECTED,
            'sample_collected_at' => $order->sample_collected_at ?? now(),
            'sample_collected_by_user_id' => $userId > 0 ? $userId : $order->sample_collected_by_user_id,
        ])->save();

        return $order;
    }

    public function start(LabOrder $order, int $userId = 0): LabOrder
    {
        $this->assertOpen($order);

        DB::transaction(function () use ($order, $userId) {
            $order->forceFill([
                'status' => LabOrder::STATUS_IN_PROGRESS,
                // Starting analysis implies the sample is in hand — backfill the
                // timestamp so the worklist never shows an impossible history.
                'sample_collected_at' => $order->sample_collected_at ?? now(),
                'sample_collected_by_user_id' => $order->sample_collected_by_user_id
                    ?: ($userId > 0 ? $userId : null),
                'started_at' => $order->started_at ?? now(),
                'started_by_user_id' => $userId > 0 ? $userId : $order->started_by_user_id,
            ])->save();

            $order->items()
                ->where('status', LabOrderItem::STATUS_PENDING)
                ->update([
                    'status' => LabOrderItem::STATUS_IN_PROGRESS,
                    'started_at' => now(),
                ]);
        });

        return $order->fresh(['items.labTest']);
    }

    /**
     * Save the technician's per-test values.
     *
     * @param  array<int, array{result_value?:string|null, result_unit?:string|null, flag?:string|null, notes?:string|null}>  $rows
     *                                                                                                                               keyed by lab_order_item id
     */
    public function saveResults(LabOrder $order, array $rows, int $userId = 0): LabOrder
    {
        if ($order->status === LabOrder::STATUS_CANCELLED) {
            throw new \RuntimeException('This order was cancelled.');
        }

        DB::transaction(function () use ($order, $rows, $userId) {
            $items = $order->items()->get()->keyBy('id');

            foreach ($rows as $itemId => $row) {
                /** @var LabOrderItem|null $item */
                $item = $items->get((int) $itemId);
                if (! $item || $item->status === LabOrderItem::STATUS_CANCELLED) {
                    continue;
                }

                $value = array_key_exists('result_value', $row) ? trim((string) $row['result_value']) : null;
                $item->result_value = $value === '' ? null : $value;
                $item->result_numeric = $this->numericOf($item->result_value);

                if (array_key_exists('result_unit', $row)) {
                    $item->result_unit = trim((string) $row['result_unit']) ?: null;
                }
                if (array_key_exists('notes', $row)) {
                    $item->notes = trim((string) $row['notes']) ?: null;
                }

                // An explicit flag from the technician always wins; otherwise
                // derive it from the numeric reference range when we can.
                $flag = array_key_exists('flag', $row) ? ($row['flag'] ?: null) : ($item->flag ?: null);
                $item->flag = $flag ?: $item->deriveFlag();

                if ($item->hasResult()) {
                    $item->status = LabOrderItem::STATUS_COMPLETED;
                    $item->completed_at = $item->completed_at ?? now();
                    $item->completed_by_user_id = $item->completed_by_user_id ?: ($userId > 0 ? $userId : null);
                } elseif ($item->status === LabOrderItem::STATUS_COMPLETED) {
                    // Result cleared again → back to in-progress.
                    $item->status = LabOrderItem::STATUS_IN_PROGRESS;
                    $item->completed_at = null;
                }

                $item->entered_by_user_id = $userId > 0 ? $userId : $item->entered_by_user_id;
                $item->save();
            }

            // Typing a result implicitly moves the order into analysis.
            if ($order->status === LabOrder::STATUS_ORDERED || $order->status === LabOrder::STATUS_SAMPLE_COLLECTED) {
                $order->forceFill([
                    'status' => LabOrder::STATUS_IN_PROGRESS,
                    'sample_collected_at' => $order->sample_collected_at ?? now(),
                    'started_at' => $order->started_at ?? now(),
                    'started_by_user_id' => $order->started_by_user_id ?: ($userId > 0 ? $userId : null),
                ])->save();
            }
        });

        return $order->fresh(['items.labTest']);
    }

    /**
     * Release the report. Every non-cancelled test must carry a result — an
     * order with a blank line is not a report a doctor can act on.
     */
    public function complete(LabOrder $order, ?string $labNote = null, int $userId = 0): LabOrder
    {
        if ($order->status === LabOrder::STATUS_CANCELLED) {
            throw new \RuntimeException('This order was cancelled.');
        }
        if ($order->status === LabOrder::STATUS_COMPLETED) {
            return $order;
        }

        $order->load('items');
        $missing = $order->items
            ->reject(fn (LabOrderItem $i) => $i->status === LabOrderItem::STATUS_CANCELLED)
            ->reject(fn (LabOrderItem $i) => $i->hasResult());

        if ($missing->isNotEmpty()) {
            throw new \RuntimeException(
                $missing->count().' test(s) still have no result — fill them in before releasing the report.'
            );
        }

        $order->forceFill([
            'status' => LabOrder::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by_user_id' => $userId > 0 ? $userId : null,
            'lab_note' => $labNote !== null ? (trim($labNote) ?: null) : $order->lab_note,
        ])->save();

        $this->notifyDoctorResultsReady($order->fresh(['items.labTest', 'patient', 'doctor']));

        return $order;
    }

    /** Doctor signs off on the report — this is what clears the "new result" dot. */
    public function review(LabOrder $order, ?string $note = null, int $userId = 0): LabOrder
    {
        if ($order->status !== LabOrder::STATUS_COMPLETED) {
            throw new \RuntimeException('Only a completed report can be reviewed.');
        }

        $order->forceFill([
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $userId > 0 ? $userId : null,
            'review_note' => $note !== null ? (trim($note) ?: null) : $order->review_note,
        ])->save();

        return $order;
    }

    /** Record that the report reached the patient (whatsapp / print / download). */
    public function markDelivered(LabOrder $order, string $channel, int $userId = 0): LabOrder
    {
        $order->forceFill([
            'delivered_at' => now(),
            'delivered_channel' => $channel,
            'delivered_by_user_id' => $userId > 0 ? $userId : null,
        ])->save();

        return $order;
    }

    /** Cancel the whole order and reverse the billing lines it created. */
    public function cancel(LabOrder $order, ?string $reason = null, int $userId = 0): LabOrder
    {
        if ($order->status === LabOrder::STATUS_COMPLETED) {
            throw new \RuntimeException('A released report cannot be cancelled.');
        }

        DB::transaction(function () use ($order, $reason, $userId) {
            foreach ($order->items()->get() as $item) {
                $this->reverseCharge($item);
                $item->forceFill(['status' => LabOrderItem::STATUS_CANCELLED])->save();
            }

            $order->forceFill([
                'status' => LabOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $userId > 0 ? $userId : null,
                'cancel_reason' => $reason ? mb_substr($reason, 0, 255) : null,
            ])->save();
        });

        return $order->fresh(['items.labTest']);
    }

    /** Drop a single test off an open order (and its charge). */
    public function removeItem(LabOrderItem $item): void
    {
        $order = $item->labOrder;
        if ($order && ! $order->isOpen()) {
            throw new \RuntimeException('This order is closed.');
        }
        if ($item->hasResult()) {
            throw new \RuntimeException('This test already has a result — cancel the order instead.');
        }

        DB::transaction(function () use ($item) {
            $this->reverseCharge($item);
            $item->delete();
        });
    }

    /**
     * Pull the visit charge a test created back off the bill. Silent when the
     * charge is already gone — reversal must stay idempotent.
     */
    protected function reverseCharge(LabOrderItem $item): void
    {
        if (! $item->visit_charge_id) {
            return;
        }

        VisitCharge::query()->whereKey($item->visit_charge_id)->delete();
        $item->visit_charge_id = null;
    }

    protected function chargeLabel(LabTest $test): string
    {
        $name = $test->name;
        $label = 'Lab: '.$name;

        return $test->code ? $label.' ('.$test->code.')' : $label;
    }

    /**
     * Best-effort in-app notification to the ordering doctor. Critical results
     * come through as a danger toast so they can't be missed on the queue.
     */
    protected function notifyDoctorResultsReady(LabOrder $order): void
    {
        try {
            $userId = $order->doctor?->user_id;
            $user = $userId ? User::query()->find($userId) : null;
            if (! $user) {
                return;
            }

            $patient = $order->patient?->name ?: '—';
            $worst = $order->worstFlag();
            $critical = $worst === LabOrderItem::FLAG_CRITICAL;

            $n = Notification::make()
                ->icon('heroicon-o-beaker')
                ->title($critical
                    ? "CRITICAL lab result — {$patient}"
                    : "Lab results ready — {$patient}")
                ->body($order->order_code.' · '.$order->items->count().' test(s)'
                    .($worst && $worst !== LabOrderItem::FLAG_NORMAL ? ' · '.strtoupper($worst) : ''))
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('Open report')
                        ->url(url('/admin/v2/lab-orders/'.$order->id)),
                ]);

            $critical ? $n->danger() : $n->success();
            $n->sendToDatabase($user);
        } catch (\Throwable) {
            // Never let a notification failure roll back a released report.
        }
    }

    /** Numeric copy of a result string, or null for "Positive" / "<5" style text. */
    protected function numericOf(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $clean = str_replace([',', ' '], '', trim($value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Pull numeric bounds off a free-text reference range so results can be
     * auto-flagged. Understands the shapes the catalog actually uses:
     *   "70-100", "70 – 100 mg/dL", "13.5-17.5"  → [low, high]
     *   "< 200", "up to 200"                     → [null, 200]
     *   "> 40", "min 40"                         → [40, null]
     * Anything else (e.g. "Negative") yields [null, null] and the technician
     * flags it by hand.
     *
     * @return array{0: float|null, 1: float|null}
     */
    public function parseReferenceRange(string $range): array
    {
        $range = trim($range);
        if ($range === '') {
            return [null, null];
        }

        // Normalise unicode dashes (and the word "to" between two numbers) to a
        // plain hyphen before matching. Only the standalone word is replaced so
        // "total" and friends survive.
        $norm = mb_strtolower($range);
        $norm = str_replace(['–', '—', '−'], '-', $norm);
        $norm = preg_replace('/\bto\b/', '-', $norm);

        if (preg_match('/(-?\d+(?:\.\d+)?)\s*-\s*(-?\d+(?:\.\d+)?)/', $norm, $m)) {
            $low = (float) $m[1];
            $high = (float) $m[2];

            return $low <= $high ? [$low, $high] : [$high, $low];
        }

        if (preg_match('/(?:<=?|under|up\s*-|below|max)\s*(-?\d+(?:\.\d+)?)/', $norm, $m)) {
            return [null, (float) $m[1]];
        }

        if (preg_match('/(?:>=?|over|above|min|at\s*least)\s*(-?\d+(?:\.\d+)?)/', $norm, $m)) {
            return [(float) $m[1], null];
        }

        return [null, null];
    }

    protected function assertOpen(LabOrder $order): void
    {
        if (! $order->isOpen()) {
            throw new \RuntimeException('This order is already '.$order->status.'.');
        }
    }
}
