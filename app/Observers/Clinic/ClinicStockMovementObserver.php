<?php

namespace App\Observers\Clinic;

use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Models\ClinicStockMovement;
use App\Models\User;
use App\Notifications\Clinic\LowStockNotification;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fires LowStockNotification once per breach. A "breach" is a movement
 * that takes on-hand below the configured min_qty_threshold_base when it
 * had been above it before. Re-firing only happens after stock comes
 * back above threshold and then crosses below again.
 *
 * Gated on `clinic.low_stock_alerts_enabled` so this can be turned off
 * in noisy dev environments.
 */
class ClinicStockMovementObserver
{
    public function created(ClinicStockMovement $movement): void
    {
        if (! (bool) config('clinic.low_stock_alerts_enabled', false)) {
            return;
        }

        $stock = $movement->clinic_item_stock_id
            ? ClinicItemStock::query()->find($movement->clinic_item_stock_id)
            : ClinicItemStock::query()
                ->where('branch_id', $movement->branch_id)
                ->where('clinic_item_id', $movement->clinic_item_id)
                ->first();

        if (! $stock || $stock->min_qty_threshold_base === null) {
            return;
        }

        $threshold = (float) $stock->min_qty_threshold_base;
        $after = (float) $movement->after_qty_base;
        $before = (float) $movement->before_qty_base;

        $isBreach = $after <= $threshold && $before > $threshold;
        if (! $isBreach) {
            return;
        }

        $item = ClinicItem::query()->find($movement->clinic_item_id);
        if (! $item) {
            return;
        }

        try {
            $this->dispatch($item, $stock, (float) $movement->after_qty_base);
        } catch (\Throwable $e) {
            Log::warning('[ClinicStockMovementObserver] low-stock notify failed', [
                'movement_id' => $movement->id,
                'err' => $e->getMessage(),
            ]);
        }
    }

    protected function dispatch(ClinicItem $item, ClinicItemStock $stock, float $observedQtyOnHand): void
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

        if ($stock->branch_id) {
            $userIds = $userIds->merge(
                DB::table('branch_user')
                    ->where('branch_id', $stock->branch_id)
                    ->pluck('user_id')
            );
        }

        // Final gate: only notify users who can actually open the linked
        // clinic-items page (it aborts 403 without view_any_clinic_items).
        // Without this, branch staff / clinic_admin lacking the permission
        // would get a low-stock toast whose link only 403s. Mirrors
        // ClinicItemsController::authorizeAccess().
        $recipients = User::query()->whereIn('id', $userIds->unique()->all())->get()
            ->filter(fn (User $u) => $u->can('view_any_clinic_items'))
            ->values();
        if ($recipients->isEmpty()) {
            return;
        }

        $payload = (new LowStockNotification($item, $stock, $observedQtyOnHand))->toDatabase($recipients->first());
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
