<?php

namespace App\Services\Inpatient;

use App\Models\Inpatient\Admission;
use App\Models\Inpatient\AdmissionBedStay;
use App\Models\Inpatient\Bed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bed assignment, transfer, and release. All mutations go through here so
 * the bed.status flag and the admission_bed_stays history stay consistent.
 */
class BedAssignmentService
{
    /**
     * Open a new bed stay for an admission. Fails if the bed is not
     * available, or if the admission already has an open stay (use
     * transfer() for that).
     */
    public function assign(Admission $admission, Bed $bed, ?User $user = null, ?string $reason = null): AdmissionBedStay
    {
        return DB::transaction(function () use ($admission, $bed, $user, $reason) {
            // Lock the bed row to prevent two concurrent assigns racing.
            $bed = Bed::query()->whereKey($bed->id)->lockForUpdate()->first();

            if (! $bed) {
                throw new RuntimeException('Bed not found.');
            }
            if ($bed->status !== Bed::STATUS_AVAILABLE) {
                throw new RuntimeException("Bed {$bed->code} is not available (status: {$bed->status}).");
            }
            if (! $bed->is_active) {
                throw new RuntimeException("Bed {$bed->code} is not active.");
            }
            if ($bed->branch_id !== $admission->branch_id) {
                throw new RuntimeException('Bed branch does not match admission branch.');
            }

            $openStay = AdmissionBedStay::query()
                ->where('admission_id', $admission->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();
            if ($openStay) {
                throw new RuntimeException('Admission already has an open bed stay. Use transfer() instead.');
            }

            $stay = AdmissionBedStay::create([
                'admission_id' => $admission->id,
                'bed_id' => $bed->id,
                'ward_id' => $bed->ward_id,
                'assigned_at' => now(),
                'daily_rate' => $bed->effectiveDailyRate(),
                'assigned_by_user_id' => $user?->id,
                'reason_for_change' => $reason,
            ]);

            $bed->update(['status' => Bed::STATUS_OCCUPIED]);

            return $stay;
        });
    }

    /**
     * Move an admission from its current bed to a new one. Closes the
     * current stay (released_at = now), frees the old bed, then opens a
     * new stay on the target bed.
     */
    public function transfer(Admission $admission, Bed $newBed, ?User $user = null, ?string $reason = null): AdmissionBedStay
    {
        return DB::transaction(function () use ($admission, $newBed, $user, $reason) {
            $this->release($admission, $user, $reason ?? 'Transferred');
            return $this->assign($admission, $newBed, $user, $reason ?? 'Transferred');
        });
    }

    /**
     * Close the admission's current bed stay and free the bed. Used by
     * discharge and as the first half of a transfer. No-op if there's
     * no open stay.
     */
    public function release(Admission $admission, ?User $user = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($admission, $user, $reason) {
            $stay = AdmissionBedStay::query()
                ->where('admission_id', $admission->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();

            if (! $stay) {
                return;
            }

            $stay->update([
                'released_at' => now(),
                'released_by_user_id' => $user?->id,
                'reason_for_change' => $reason ?? $stay->reason_for_change,
            ]);

            // Bed goes back to cleaning so housekeeping flags it; admin can
            // flip to available when ready. (Discharge skips this if we
            // want immediate availability — kept as cleaning for safety.)
            $bed = Bed::query()->whereKey($stay->bed_id)->lockForUpdate()->first();
            if ($bed) {
                $bed->update(['status' => Bed::STATUS_CLEANING]);
            }
        });
    }
}
