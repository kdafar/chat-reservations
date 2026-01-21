<?php

namespace App\Services\Clinic;

use App\Models\DoctorCompensationLedger;
use App\Models\DoctorCompensationProfile;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class DoctorCompensationService
{
    public function sync(Visit $visit, ?int $actorUserId = null): ?DoctorCompensationLedger
    {
        if (! config('clinic.doctor_comp_enabled', false)) {
            return null;
        }

        if (config('clinic.doctor_comp_only_on_completed', true) && $visit->status !== 'completed') {
            return null;
        }

        $doctorId = (int) ($visit->doctor_id ?? 0);
        if (! $doctorId) {
            return null;
        }

        return DB::transaction(function () use ($visit, $doctorId) {
            // Single profile per doctor
            $profile = DoctorCompensationProfile::query()
                ->where('doctor_id', $doctorId)->where('is_active', 1)->first();

            // snapshot visit financials (audit-proof)
            $fees = (float) ($visit->fees_total ?? 0);
            $discount = (float) ($visit->discount_total ?? 0);
            $cost = (float) ($visit->items_cost_total ?? 0);
            $profit = (float) ($visit->profit_total ?? 0);

            $type = $profile?->type ?? 'salary';          // default safe
            $basis = $profile?->basis ?? 'fees_only';
            $rate = $profile?->percentage_rate;

            $cut = 0.0;

            if ($type === 'percentage') {
                $base = 0.0;

                if ($basis === 'net_profit') {
                    $base = $profit;
                } else {
                    // fees_only => fees - discount
                    $base = max(0.0, $fees - $discount);
                }

                $pct = (float) ($rate ?? 0);
                $cut = $base * ($pct / 100.0);

                // defensive: no negative payouts
                if ($cut < 0) {
                    $cut = 0.0;
                }
            }

            $payload = [
                'doctor_id' => $doctorId,
                'branch_id' => $visit->branch_id ?? null,

                'type_snapshot' => $type,
                'basis_snapshot' => $basis,
                'rate_snapshot' => $type === 'percentage' ? (float) ($rate ?? 0) : null,

                'fees_snapshot' => $fees,
                'discount_snapshot' => $discount,
                'cost_snapshot' => $cost,
                'profit_snapshot' => $profit,

                'doctor_cut_amount' => $cut,
            ];

            // Idempotent: one ledger per visit
            return DoctorCompensationLedger::query()->updateOrCreate(
                ['visit_id' => $visit->id],
                $payload
            );
        });
    }
}
