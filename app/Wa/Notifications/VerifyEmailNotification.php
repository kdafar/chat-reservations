<?php

namespace App\Wa\Notifications;

use App\Wa\Mail\VerifyEmailMail; // We will create this next
use App\Wa\Notifications\Concerns\TracksSystemEmails; // Import the Trait
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable, TracksSystemEmails; // Use the Trait

    public function __construct() {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        // ONE-LINE TRACKING:
        $subject = 'Verify Email Address'; // Define subject for logging
        $trackingUrl = $this->generateTrackingUrl($notifiable, $subject);

        return (new VerifyEmailMail($notifiable, $verificationUrl))
            ->to($notifiable->email)
            ->subject($subject)
            ->with(['trackingUrl' => $trackingUrl]);
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
