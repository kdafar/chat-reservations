<?php

namespace App\Observers;

use App\Models\Doctor;
use Illuminate\Support\Facades\DB;

class DoctorObserver
{
    /**
     * Mirror the doctor's branch into the `branch_user` pivot so every piece
     * of code that asks "what branch(es) does this user belong to" (widgets,
     * notification audience, BelongsToBranchScope, etc.) gets a consistent
     * answer.
     */
    public function created(Doctor $doctor): void
    {
        $this->syncBranchPivot($doctor);
    }

    public function updated(Doctor $doctor): void
    {
        if ($doctor->wasChanged(['user_id', 'branch_id'])) {
            $this->syncBranchPivot($doctor, $doctor->getOriginal('user_id'), $doctor->getOriginal('branch_id'));
        }
    }

    /**
     * When a Doctor is deleted, also delete the linked auth User. The User
     * row is created automatically alongside the Doctor on creation, so its
     * lifecycle is tied to the Doctor's. (Deleting the user cascades onto
     * `branch_user` via FK, so no separate cleanup needed there.)
     */
    public function deleted(Doctor $doctor): void
    {
        if (! $doctor->user_id) {
            return;
        }

        DB::transaction(function () use ($doctor) {
            $user = $doctor->user()->first();
            if ($user) {
                $user->delete();
            }
        });
    }

    protected function syncBranchPivot(Doctor $doctor, ?int $previousUserId = null, ?int $previousBranchId = null): void
    {
        // Tear down the stale link first (only the row added by this doctor,
        // not any unrelated branch_user rows the user might have).
        if ($previousUserId && $previousBranchId) {
            DB::table('branch_user')
                ->where('user_id', $previousUserId)
                ->where('branch_id', $previousBranchId)
                ->delete();
        }

        if (! $doctor->user_id || ! $doctor->branch_id) {
            return;
        }

        // updateOrInsert because some installations declare `branch_user` with
        // a composite primary key and we don't want a duplicate-key crash on
        // re-sync.
        DB::table('branch_user')->updateOrInsert(
            ['user_id' => $doctor->user_id, 'branch_id' => $doctor->branch_id],
            [],
        );
    }
}
