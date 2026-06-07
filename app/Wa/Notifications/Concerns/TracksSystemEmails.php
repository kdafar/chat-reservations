<?php

namespace App\Wa\Notifications\Concerns;

use App\Wa\Models\SupportEmailLog;
use Illuminate\Support\Facades\URL;

trait TracksSystemEmails
{
    /**
     * Creates a tracking log entry and returns the signed tracking pixel URL.
     *
     * * @param object $notifiable The user or entity being notified
     * @param  string  $subject  The subject line of the email
     * @return string The signed URL to inject into the view
     */
    protected function generateTrackingUrl(object $notifiable, string $subject): string
    {
        // 1. Resolve the Email Address
        // Try the standard routeNotificationFor, then the email property, then fallback to user relation
        $email = $notifiable->routeNotificationFor('mail', $this)
              ?? $notifiable->email
              ?? $notifiable->user->email
              ?? null;

        if (! $email) {
            return '';
        }

        // 2. Resolve the User ID (if applicable)
        $userId = $notifiable->id ?? $notifiable->user->id ?? null;
        // If the notifiable is Anonymous (route method), userId might be null, which is fine.

        // 3. Create the Log
        $log = SupportEmailLog::create([
            'broadcast_id' => null, // Null because this is a triggered system email, not a bulk broadcast
            'user_id' => $userId,
            'email' => $email,
            'subject' => $subject,
            'status' => 'sent',
            'sent_at' => now(),
            'type' => 'system_notification',
        ]);

        // 4. Return the Signed URL
        return URL::signedRoute('support-email.track', ['log' => $log->id]);
    }
}
