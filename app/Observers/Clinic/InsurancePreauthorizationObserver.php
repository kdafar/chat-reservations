<?php

namespace App\Observers\Clinic;

use App\Models\Insurance\InsurancePreauthorization;
use App\Models\User;
use App\Notifications\Clinic\PreauthDecisionNotification;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fires PreauthDecisionNotification when a pre-authorization transitions
 * into a terminal decision state (approved / partial / rejected). Only
 * fires on the transition into the state, not on subsequent edits of an
 * already-decided pre-auth.
 *
 * Audience: the original requester + any user with insurance-management
 * permissions + admin/super_admin/clinic_admin.
 */
class InsurancePreauthorizationObserver
{
    private const DECISION_STATES = [
        InsurancePreauthorization::STATUS_APPROVED,
        InsurancePreauthorization::STATUS_PARTIALLY_APPROVED,
        InsurancePreauthorization::STATUS_REJECTED,
    ];

    public function updated(InsurancePreauthorization $preauth): void
    {
        if (! $preauth->wasChanged('status')) {
            return;
        }

        if (! in_array($preauth->status, self::DECISION_STATES, true)) {
            return;
        }

        // Guard against re-firing if the row was edited but the status
        // was already a decision before this save (e.g. updating notes).
        $previous = $preauth->getOriginal('status');
        if (in_array($previous, self::DECISION_STATES, true)) {
            return;
        }

        try {
            $this->dispatch($preauth);
        } catch (\Throwable $e) {
            Log::warning('[InsurancePreauthorizationObserver] decision notify failed', [
                'preauth_id' => $preauth->id,
                'err' => $e->getMessage(),
            ]);
        }
    }

    protected function dispatch(InsurancePreauthorization $preauth): void
    {
        $userIds = collect();

        // Filter to roles that actually exist (Spatie throws on missing names).
        $candidateRoles = ['admin', 'super_admin', 'clinic_admin'];
        $existingRoles = \Spatie\Permission\Models\Role::query()
            ->whereIn('name', $candidateRoles)
            ->pluck('name')
            ->all();
        if (! empty($existingRoles)) {
            $userIds = $userIds->merge(User::role($existingRoles)->pluck('id'));
        }

        // Spatie throws if any permission name in the array doesn't exist —
        // filter to only ones present in the DB before querying.
        $candidatePerms = ['insurance_manage_claims', 'insurance_manage_policies'];
        $existingPerms = \Spatie\Permission\Models\Permission::query()
            ->whereIn('name', $candidatePerms)
            ->pluck('name')
            ->all();
        if (! empty($existingPerms)) {
            $userIds = $userIds->merge(
                User::query()->permission($existingPerms)->pluck('id')
            );
        }

        if ($preauth->requested_by_user_id) {
            $userIds->push($preauth->requested_by_user_id);
        }

        $recipients = User::query()->whereIn('id', $userIds->unique()->all())->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $payload = (new PreauthDecisionNotification($preauth))->toDatabase($recipients->first());
        $now = now();
        $rows = $recipients->map(fn (User $u) => [
            'id' => (string) Str::uuid(),
            'type' => FilamentDatabaseNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $u->id,
            'data' => json_encode($payload),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('notifications')->insert($rows);
    }
}
