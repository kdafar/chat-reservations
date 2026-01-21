<?php

namespace App\Services\Clinic;

use App\Models\ClinicPackage;
use App\Models\ClinicPackageItem;
use App\Models\Visit;
use App\Models\VisitPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitPackageService
{
    public function enabled(): bool
    {
        return (bool) config('clinic.packages_enabled', true);
    }

    /**
     * Apply multiple packages to a visit.
     *
     * $lines:
     * [
     *   ['clinic_package_id' => 12, 'qty' => 1],
     *   ['clinic_package_id' => 15, 'qty' => 2],
     * ]
     *
     * Behavior:
     * - Upsert visit_packages (price snapshot)
     * - Build required items (qty_base summed per clinic_item_id)
     * - Create/update pending VisitStockRequest (snapshotted lines)
     * - Set visit status to awaiting_stock (pause until items issued)
     */
    public function applyPackages(
        Visit $visit,
        array $lines,
        int $performedByUserId = 0,
        ?string $notes = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $visitId = (int) $visit->id;
        if ($visitId <= 0) {
            return;
        }

        $normalized = $this->normalizeLines($lines);
        if ($normalized->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($visitId, $normalized, $performedByUserId, $notes) {
            /** @var Visit $freshVisit */
            $freshVisit = Visit::query()->lockForUpdate()->findOrFail($visitId);

            $branchId = (int) ($freshVisit->branch_id ?? 0);

            // Load packages + items
            $packages = ClinicPackage::query()
                ->whereIn('id', $normalized->pluck('clinic_package_id')->all())
                ->where('is_active', true)
                ->with(['items'])
                ->get()
                ->keyBy('id');

            // 1) Upsert visit_packages (snapshot)
            foreach ($normalized as $ln) {
                $pkgId = (int) $ln['clinic_package_id'];
                $qty = (float) $ln['qty'];

                /** @var ClinicPackage|null $pkg */
                $pkg = $packages->get($pkgId);
                if (! $pkg) {
                    continue;
                }

                // Branch guard (if you want strict branch-only packages)
                if ($pkg->branch_id && $branchId > 0 && (int) $pkg->branch_id !== $branchId) {
                    continue;
                }

                $unit = (float) ($pkg->default_price ?? 0);
                $lineTotal = $qty * $unit;

                // Unique(visit_id, clinic_package_id) => update existing
                $vp = VisitPackage::query()->lockForUpdate()
                    ->where('visit_id', $freshVisit->id)
                    ->where('clinic_package_id', $pkgId)
                    ->first();

                if (! $vp) {
                    $vp = new VisitPackage;
                    $vp->visit_id = $freshVisit->id;
                    $vp->clinic_package_id = $pkgId;
                    $vp->branch_id = $branchId ?: null;
                    $vp->added_by_user_id = $performedByUserId > 0 ? $performedByUserId : null;

                    $vp->qty = $qty;
                    $vp->unit_price_snapshot = $unit;
                    $vp->line_total = $lineTotal;
                    $vp->save();
                } else {
                    // Conservative behavior: increment qty rather than overwrite
                    $newQty = ((float) $vp->qty) + $qty;

                    // Keep snapshot unit price stable if already set; otherwise set from package
                    $existingUnit = (float) ($vp->unit_price_snapshot ?? 0);
                    if ($existingUnit <= 0) {
                        $existingUnit = $unit;
                        $vp->unit_price_snapshot = $existingUnit;
                    }

                    $vp->qty = $newQty;
                    $vp->line_total = $newQty * $existingUnit;

                    if ($performedByUserId > 0 && ! $vp->added_by_user_id) {
                        $vp->added_by_user_id = $performedByUserId;
                    }

                    $vp->save();
                }
            }

            // 2) Build required items snapshot from package definitions
            // We snapshot required items into the stock request lines.
            $requirements = $this->buildRequirementsFromPackages($packages, $normalized);

            if ($requirements->isEmpty()) {
                // Still move status? No. Nothing required.
                return;
            }

            // 3) Create/update pending stock request + move visit to awaiting_stock
            app(VisitStockRequestService::class)->createForVisit(
                $freshVisit,
                $requirements->values()->all(),
                $performedByUserId,
                $notes,
                true
            );
        });
    }

    protected function normalizeLines(array $lines): Collection
    {
        return collect($lines)
            ->map(fn ($r) => [
                'clinic_package_id' => (int) ($r['clinic_package_id'] ?? 0),
                'qty' => (float) ($r['qty'] ?? 1),
            ])
            ->filter(fn ($r) => $r['clinic_package_id'] > 0 && $r['qty'] > 0)
            ->groupBy('clinic_package_id')
            ->map(fn ($rows, $pkgId) => [
                'clinic_package_id' => (int) $pkgId,
                'qty' => (float) $rows->sum(fn ($x) => (float) $x['qty']),
            ])
            ->values();
    }

    /**
     * Returns collection of:
     * [
     *   ['clinic_item_id' => 10, 'qty_base' => 2.5000],
     *   ...
     * ]
     */
    protected function buildRequirementsFromPackages(Collection $packagesById, Collection $normalizedLines): Collection
    {
        $acc = collect();

        foreach ($normalizedLines as $ln) {
            $pkgId = (int) $ln['clinic_package_id'];
            $pkgQty = (float) $ln['qty'];

            /** @var ClinicPackage|null $pkg */
            $pkg = $packagesById->get($pkgId);
            if (! $pkg) {
                continue;
            }

            foreach (($pkg->items ?? []) as $it) {
                /** @var ClinicPackageItem $it */
                $itemId = (int) ($it->clinic_item_id ?? 0);
                $qtyBase = (float) ($it->qty_base ?? 0);

                if ($itemId <= 0 || $qtyBase <= 0) {
                    continue;
                }

                $delta = $qtyBase * $pkgQty;

                $acc[$itemId] = (float) ($acc[$itemId] ?? 0) + $delta;
            }
        }

        return collect($acc)->map(fn ($qty, $itemId) => [
            'clinic_item_id' => (int) $itemId,
            'qty_base' => (float) $qty,
        ])->values();
    }
}
