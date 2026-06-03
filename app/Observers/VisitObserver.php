<?php

namespace App\Observers;

use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Visit;
use App\Services\Clinic\FollowUpService;
use App\Services\Insurance\InsuranceService;
use Illuminate\Support\Facades\Log;

class VisitObserver
{
    public function saved(Visit $visit): void
    {
        $this->syncFollowUpPlan($visit);
        $this->autoDraftInsuranceClaim($visit);
    }

    protected function syncFollowUpPlan(Visit $visit): void
    {
        $followUpChanged = $visit->wasChanged('follow_up_date');
        $statusBecameCompleted = $visit->wasChanged('status') && ($visit->status === Visit::STATUS_COMPLETED);

        if (! $followUpChanged && ! $statusBecameCompleted) {
            return;
        }

        if (! $visit->follow_up_date) {
            return;
        }

        if (config('clinic.follow_up_only_on_completed', false) && $visit->status !== Visit::STATUS_COMPLETED) {
            return;
        }

        app(FollowUpService::class)->syncFromVisit($visit, null, null);
    }

    /**
     * On visit completion, draft an InsuranceClaim against the patient's
     * primary active policy. Idempotent (InsuranceService::createClaimFromVisit
     * returns the existing non-void claim if one exists). Skipped silently when:
     *   - the feature flag is off
     *   - the status didn't just change to completed
     *   - the patient has no active policy
     *   - reception explicitly skipped (insurance_claim_skipped_at is set)
     *
     * Errors are logged but swallowed — never break the visit save path.
     */
    protected function autoDraftInsuranceClaim(Visit $visit): void
    {
        if (! config('clinic.insurance_auto_claim_on_complete', true)) {
            return;
        }

        if (! $visit->wasChanged('status') || $visit->status !== Visit::STATUS_COMPLETED) {
            return;
        }

        if (! $visit->patient_id) {
            return;
        }

        if (! empty($visit->insurance_claim_skipped_at)) {
            return;
        }

        try {
            $policy = PatientInsurancePolicy::query()
                ->where('patient_id', $visit->patient_id)
                ->active()
                ->orderByDesc('is_primary')
                ->orderBy('priority')
                ->orderByDesc('id')
                ->first();

            if (! $policy) {
                return;
            }

            $alreadyClaimed = InsuranceClaim::query()
                ->where('visit_id', $visit->id)
                ->where('status', '!=', InsuranceClaim::STATUS_VOID)
                ->exists();

            if ($alreadyClaimed) {
                return;
            }

            $actor = $this->resolveActor($visit);
            if (! $actor) {
                // Truly no actor available (no auth, no admin, no any user) —
                // skip rather than violate the state-log FK. Should be
                // ~impossible in practice but logged for diagnosis.
                Log::warning('[VisitObserver] auto-claim skipped: no actor available', [
                    'visit_id' => $visit->id,
                ]);
                return;
            }

            app(InsuranceService::class)->createClaimFromVisit($visit, $policy, $actor);
        } catch (\Throwable $e) {
            Log::error('[VisitObserver] auto-draft insurance claim failed', [
                'visit_id' => $visit->id,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort actor resolution for system-driven visit completions
     * (cron jobs, queue workers, API requests without a session). Tries in
     * order: the auth session, the user who originally accepted the visit,
     * the visit's doctor's linked user, any admin/super_admin, then any user.
     */
    protected function resolveActor(\App\Models\Visit $visit): ?\App\Models\User
    {
        $actorId = (int) (auth()->id() ?? 0);
        if ($actorId && ($u = \App\Models\User::find($actorId))) {
            return $u;
        }

        if (! empty($visit->accepted_by_user_id)
            && ($u = \App\Models\User::find($visit->accepted_by_user_id))
        ) {
            return $u;
        }

        if (! empty($visit->updated_by_user_id)
            && ($u = \App\Models\User::find($visit->updated_by_user_id))
        ) {
            return $u;
        }

        $doctorUserId = $visit->doctor?->user_id;
        if ($doctorUserId && ($u = \App\Models\User::find($doctorUserId))) {
            return $u;
        }

        $adminRoles = \Spatie\Permission\Models\Role::query()
            ->whereIn('name', ['admin', 'super_admin', 'clinic_admin'])
            ->pluck('name')
            ->all();
        if (! empty($adminRoles)) {
            $admin = \App\Models\User::role($adminRoles)->orderBy('id')->first();
            if ($admin) {
                return $admin;
            }
        }

        return \App\Models\User::query()->orderBy('id')->first();
    }
}
