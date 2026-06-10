<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Visit;
use App\Models\VisitPayment;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Notifies the doctor's linked auth user when the consultation fee for a
 * visit has actually been paid. Triggered on both `created` (e.g. immediate
 * payment) and `updated` (status transitioning to "paid"). De-duplicated via
 * a per-payment cache lock so a flurry of status updates can't spam the
 * doctor.
 */
class VisitPaymentObserver
{
    public function created(VisitPayment $payment): void
    {
        $this->maybeNotify($payment);
    }

    public function updated(VisitPayment $payment): void
    {
        // Only fire when the row just transitioned into a "paid" state.
        if (! $payment->wasChanged('status')) {
            return;
        }

        $this->maybeNotify($payment);
    }

    protected function maybeNotify(VisitPayment $payment): void
    {
        if (($payment->status ?? null) !== 'paid') {
            return;
        }

        if (($payment->kind ?? VisitPayment::KIND_CONSULTATION) !== VisitPayment::KIND_CONSULTATION) {
            return;
        }

        // De-dupe: only ever notify once per payment row.
        $lockKey = sprintf('doctor:consultation-notify:%d', $payment->id);
        if (! cache()->add($lockKey, 1, now()->addDays(1))) {
            return;
        }

        $visit = $payment->visit()->first();
        if (! $visit || ! $visit->doctor_id) {
            return;
        }

        $doctorUserId = DB::table('doctors')
            ->where('id', $visit->doctor_id)
            ->value('user_id');

        if (! $doctorUserId) {
            return;
        }

        $patientName = optional($visit->patient)->name ?: '#'.$visit->patient_id;
        $branchName = optional($visit->branch)->name ?: '-';

        $payload = Notification::make()
            ->title(__('notifications.consultation_paid.title'))
            ->body(__('notifications.consultation_paid.body', [
                'patient' => $patientName,
                'branch' => $branchName,
            ]))
            ->icon('heroicon-o-banknotes')
            ->iconColor('success')
            ->actions([
                Action::make('open_queue')
                    ->label(__('notifications.consultation_paid.action_open'))
                    ->url(url('/admin/v2/waiting-patients'), shouldOpenInNewTab: false)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();

        $now = now();

        // Insert directly to bypass the queue (multitenancy middleware
        // intercepts queued notifications without a tenant context).
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => FilamentDatabaseNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $doctorUserId,
            'data' => json_encode($payload),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
