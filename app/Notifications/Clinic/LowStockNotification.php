<?php

namespace App\Notifications\Clinic;

use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired once when a stock movement drops on-hand below the configured
 * `min_qty_threshold_base`. The observer guards against re-firing while
 * the level stays low (only re-fires if stock first comes back above
 * threshold and then drops again).
 */
class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected ClinicItem $item,
        protected ClinicItemStock $stock,
        protected ?float $observedQtyOnHand = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $name = $this->item->localized_name ?: "Item #{$this->item->id}";
        $onHand = number_format(
            (float) ($this->observedQtyOnHand ?? $this->stock->qty_on_hand_base),
            3
        );
        $threshold = number_format((float) $this->stock->min_qty_threshold_base, 3);

        return FilamentNotification::make()
            ->title("Low stock: {$name}")
            ->body("On-hand {$onHand} ≤ reorder threshold {$threshold} (branch #{$this->stock->branch_id}).")
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Open item')
                    ->url("/admin/clinic-items/{$this->item->id}/edit")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
