<?php

namespace App\Observers;

use App\Models\Visit;
use App\Services\Clinic\FollowUpService;

class VisitObserver
{
    public function saved(Visit $visit): void
    {
        // Only act when follow_up_date changes OR visit becomes completed
        $followUpChanged = $visit->wasChanged('follow_up_date');
        $statusBecameCompleted = $visit->wasChanged('status') && ($visit->status === 'completed');

        if (! $followUpChanged && ! $statusBecameCompleted) {
            return;
        }

        // If no follow-up date, do nothing
        if (! $visit->follow_up_date) {
            return;
        }

        // If you want “only on completed”, gate it:
        if (config('clinic.follow_up_only_on_completed', false) && $visit->status !== 'completed') {
            return;
        }

        app(FollowUpService::class)->syncFromVisit($visit, null, null);
    }
}
