<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\User;
use App\Services\BookingAudienceResolver;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingObserver
{
    public function __construct(protected BookingAudienceResolver $audience) {}

    public function created(Booking $booking): void
    {
        // Skip transient holds — only notify on real customer-facing bookings.
        if (in_array($booking->status, [Booking::S_DRAFT, Booking::S_HOLD], true)) {
            return;
        }

        $recipients = $this->audience->for($booking);

        if ($recipients->isEmpty()) {
            return;
        }

        $title = __('notifications.booking.created.title');
        $body = __('notifications.booking.created.body', [
            'code' => $booking->booking_code ?: '#'.$booking->id,
            'date' => optional($booking->res_date)->format('Y-m-d') ?: '-',
            'time' => $booking->res_time ?: '-',
            'branch' => optional($booking->branch)->name ?: '-',
        ]);

        $url = url('/admin/bookings/'.$booking->id.'/edit');

        $payload = Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-calendar-days')
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->label(__('notifications.booking.created.action_view'))
                    ->url($url, shouldOpenInNewTab: false)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();

        $now = now();
        $rows = $recipients->map(fn (User $user) => [
            'id' => (string) Str::uuid(),
            'type' => FilamentDatabaseNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($payload),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Insert directly to bypass the queue (multitenancy middleware
        // intercepts queued notifications without a tenant context).
        DB::table('notifications')->insert($rows);
    }
}
