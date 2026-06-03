<?php

namespace App\Notifications\Clinic;

use App\Models\Insurance\InsurancePreauthorization;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired when an insurance pre-authorization moves into a terminal decision
 * state (approved / partially_approved / rejected). Target audience is
 * the requester and any insurance admins.
 */
class PreauthDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(protected InsurancePreauthorization $preauth) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $statusLabel = match ($this->preauth->status) {
            InsurancePreauthorization::STATUS_APPROVED => 'Approved',
            InsurancePreauthorization::STATUS_PARTIALLY_APPROVED => 'Partially Approved',
            InsurancePreauthorization::STATUS_REJECTED => 'Rejected',
            default => ucfirst((string) $this->preauth->status),
        };

        $color = match ($this->preauth->status) {
            InsurancePreauthorization::STATUS_APPROVED => 'success',
            InsurancePreauthorization::STATUS_PARTIALLY_APPROVED => 'warning',
            InsurancePreauthorization::STATUS_REJECTED => 'danger',
            default => 'gray',
        };

        $ref = $this->preauth->reference_no ?? "#{$this->preauth->id}";
        $body = $this->preauth->approved_amount !== null
            ? "Approved {$this->preauth->approved_amount} KWD. ".trim((string) $this->preauth->decision_notes)
            : trim((string) $this->preauth->decision_notes);

        return FilamentNotification::make()
            ->title("Pre-auth {$ref}: {$statusLabel}")
            ->body($body !== '' ? $body : 'No decision notes.')
            ->icon('heroicon-o-document-check')
            ->iconColor($color)
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Open pre-auth')
                    ->url("/admin/insurance/preauthorizations/{$this->preauth->id}/edit")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
