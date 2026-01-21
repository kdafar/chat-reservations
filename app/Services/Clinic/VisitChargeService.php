<?php

namespace App\Services\Clinic;

use App\Models\Visit;
use App\Models\VisitCharge;
use Illuminate\Support\Facades\DB;

class VisitChargeService
{
    public function addCharge(
        Visit $visit,
        string $label,
        float $qty,
        float $unitPrice,
        int $performedByUserId = 0,
    ): VisitCharge {
        $visitId = (int) $visit->id;
        if ($visitId <= 0) {
            throw new \RuntimeException('Invalid visit.');
        }

        $label = trim($label);
        if ($label === '') {
            throw new \RuntimeException('Charge label is required.');
        }

        if ($qty <= 0) {
            throw new \RuntimeException('Charge qty must be > 0.');
        }

        if ($unitPrice < 0) {
            throw new \RuntimeException('Unit price cannot be negative.');
        }

        return DB::transaction(function () use ($visitId, $label, $qty, $unitPrice, $performedByUserId) {
            /** @var Visit $fresh */
            $fresh = Visit::query()->lockForUpdate()->findOrFail($visitId);

            $c = new VisitCharge;
            $c->visit_id = $fresh->id;
            $c->branch_id = $fresh->branch_id ?: null;

            $c->label = $label;
            $c->qty = $qty;
            $c->unit_price_snapshot = $unitPrice;
            $c->line_total = $qty * $unitPrice;

            $c->added_by_user_id = $performedByUserId > 0 ? $performedByUserId : null;
            $c->save();

            return $c;
        });
    }
}
