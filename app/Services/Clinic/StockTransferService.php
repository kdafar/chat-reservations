<?php

namespace App\Services\Clinic;

use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

/**
 * Inter-branch stock transfers for a clinic (hub → branch is the common path).
 * Dispatching a transfer consumes from the source branch and restocks the
 * destination branch, recording transfer_out / transfer_in stock movements
 * (which the accounting observer ignores — a transfer is value-neutral).
 */
class StockTransferService
{
    public function __construct(protected ClinicStockService $stock) {}

    /** The clinic's designated hub branch id, or null if none set. */
    public function hubBranchId(?int $partnerId): ?int
    {
        if (! $partnerId) {
            return null;
        }

        return Branch::query()->withoutGlobalScopes()
            ->where('partner_id', $partnerId)->where('is_hub', true)
            ->value('id');
    }

    /**
     * Create a pending transfer. $lines = [['clinic_item_id'=>, 'qty_base'=>], ...].
     * $fromBranchId defaults to the clinic's hub.
     */
    public function create(int $partnerId, ?int $fromBranchId, int $toBranchId, array $lines, int $requestedBy = 0, ?int $visitId = null, ?string $notes = null): StockTransfer
    {
        $fromBranchId = $fromBranchId ?: $this->hubBranchId($partnerId);
        if (! $fromBranchId) {
            throw new \RuntimeException('No source branch (set a hub for this clinic, or pick a source branch).');
        }
        if ($fromBranchId === $toBranchId) {
            throw new \RuntimeException('Source and destination branches must differ.');
        }

        // Both branches must belong to the clinic.
        $branchPartners = Branch::query()->withoutGlobalScopes()
            ->whereIn('id', [$fromBranchId, $toBranchId])->pluck('partner_id', 'id');
        foreach ([$fromBranchId, $toBranchId] as $bid) {
            if ((int) ($branchPartners[$bid] ?? -1) !== (int) $partnerId) {
                throw new \RuntimeException('Both branches must belong to the same clinic.');
            }
        }

        $clean = collect($lines)
            ->map(fn ($l) => ['clinic_item_id' => (int) ($l['clinic_item_id'] ?? 0), 'qty_base' => (float) ($l['qty_base'] ?? 0)])
            ->filter(fn ($l) => $l['clinic_item_id'] > 0 && $l['qty_base'] > 0)
            ->values();
        if ($clean->isEmpty()) {
            throw new \RuntimeException('Add at least one item to transfer.');
        }

        return DB::transaction(function () use ($partnerId, $fromBranchId, $toBranchId, $clean, $requestedBy, $visitId, $notes) {
            $transfer = StockTransfer::create([
                'partner_id' => $partnerId,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'status' => StockTransfer::STATUS_PENDING,
                'visit_id' => $visitId,
                'requested_by_user_id' => $requestedBy ?: null,
                'notes' => $notes,
            ]);
            foreach ($clean as $l) {
                $transfer->lines()->create($l);
            }

            return $transfer;
        });
    }

    /**
     * Dispatch: move each line's qty from the source branch to the destination.
     * Atomic — if the source is short on any line, the whole dispatch rolls back.
     */
    public function dispatch(StockTransfer $transfer, int $userId = 0): StockTransfer
    {
        if ($transfer->status !== StockTransfer::STATUS_PENDING) {
            throw new \RuntimeException('Only a pending transfer can be dispatched.');
        }

        return DB::transaction(function () use ($transfer, $userId) {
            $transfer->loadMissing('lines');
            foreach ($transfer->lines as $line) {
                $item = ClinicItem::query()->withoutGlobalScopes()->find($line->clinic_item_id);
                if (! $item) {
                    continue;
                }
                $qty = (float) $line->qty_base;
                $note = "Transfer #{$transfer->id} → branch {$transfer->to_branch_id}";
                // consume() throws if the source branch is short → whole tx rolls back.
                $this->stock->consume((int) $transfer->from_branch_id, $item, $qty, $userId, $note, $transfer, 'transfer_out');
                $this->stock->restock((int) $transfer->to_branch_id, $item, null, $qty, $userId, "Transfer #{$transfer->id} ← branch {$transfer->from_branch_id}", $transfer, 'transfer_in');
            }

            $transfer->forceFill([
                'status' => StockTransfer::STATUS_DISPATCHED,
                'dispatched_by_user_id' => $userId ?: null,
                'dispatched_at' => now(),
            ])->save();

            return $transfer;
        });
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== StockTransfer::STATUS_PENDING) {
            throw new \RuntimeException('Only a pending transfer can be cancelled.');
        }
        $transfer->forceFill(['status' => StockTransfer::STATUS_CANCELLED])->save();

        return $transfer;
    }
}
