<?php

namespace App\Livewire;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Polls the notifications table for the current user and converts any new
 * unread entry into a Filament flash toast (which appears at the top of the
 * page). Lives in the panel layout so it's mounted on every authenticated
 * Filament page.
 */
class BookingNotificationToaster extends Component
{
    /** ISO-8601 timestamp marking the latest notification we've already shown as a toast. */
    public string $cursor = '';

    public function mount(): void
    {
        // Initialize cursor so we don't replay every historical unread
        // notification on first mount — we only want NEW ones from here on.
        $latest = $this->latestUnreadCreatedAt();
        $this->cursor = $latest ? $latest->toIso8601String() : now()->toIso8601String();
    }

    public function poll(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $cursorAt = $this->cursor ? Carbon::parse($this->cursor) : now();

        $rows = DB::table('notifications')
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->where('created_at', '>', $cursorAt)
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            $data = json_decode($row->data, true) ?: [];

            $title = (string) ($data['title'] ?? __('notifications.booking.created.title'));
            $body = (string) ($data['body'] ?? '');
            $icon = (string) ($data['icon'] ?? 'heroicon-o-calendar-days');
            $color = (string) ($data['iconColor'] ?? 'success');

            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->icon($icon)
                ->iconColor($color)
                ->duration(10000);

            $url = $this->extractActionUrl($data);
            if ($url) {
                $notification->actions([
                    Action::make('view')
                        ->label(__('notifications.booking.created.action_view'))
                        ->url($url, shouldOpenInNewTab: false)
                        ->markAsRead(),
                ]);
            }

            $notification->send();

            $this->cursor = Carbon::parse($row->created_at)->toIso8601String();
        }

        // Tell the page to play the chime once for this batch.
        $this->dispatch('booking-notification-toasted');
    }

    public function render()
    {
        return <<<'BLADE'
        <div wire:poll.3s="poll" class="hidden"></div>
        BLADE;
    }

    protected function latestUnreadCreatedAt(): ?Carbon
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $row = DB::table('notifications')
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at')
            ->first(['created_at']);

        return $row ? Carbon::parse($row->created_at) : null;
    }

    protected function extractActionUrl(array $data): ?string
    {
        foreach ((array) ($data['actions'] ?? []) as $action) {
            if (! empty($action['url'])) {
                return (string) $action['url'];
            }
        }

        return null;
    }
}
