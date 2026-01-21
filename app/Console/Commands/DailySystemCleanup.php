<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\RestaurantTable;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailySystemCleanup extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'clinic:daily-cleanup {--dry-run : Print what would happen without saving}';

    /**
     * The console command description.
     */
    protected $description = 'Performs nightly system hygiene: discharges stale visits, cancels no-shows, and releases ghost tables.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Daily System Cleanup...');
        $this->newLine();

        // 1. Discharge visits started yesterday (or older) that are still open
        $this->dischargeStaleVisits();
        $this->newLine();

        // 2. Cancel bookings scheduled for yesterday (or older) that never checked in
        $this->markStaleBookings();
        $this->newLine();

        // 3. Unlock tables that say 'occupied' but have nobody in them
        $this->releaseGhostTables();
        $this->newLine();

        $this->info('System Cleanup Complete.');
    }

    /**
     * Task 1: Auto-complete visits started before today.
     */
    protected function dischargeStaleVisits()
    {
        $this->info('--- Task 1: Discharging Stale Visits ---');

        $staleVisits = Visit::query()
            ->whereNotNull('service_started_at')
            ->whereNull('completed_at')
            ->whereDate('service_started_at', '<', now()->toDateString())
            ->with(['booking'])
            ->get();

        $count = $staleVisits->count();

        if ($count === 0) {
            $this->info('No stale visits found.');

            return;
        }

        $this->info("Found {$count} stale visits.");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($staleVisits as $visit) {
            $booking = $visit->booking;

            if (! $booking) {
                // Orphaned visit? Just close it.
                if (! $this->option('dry-run')) {
                    $visit->update(['status' => 'completed', 'completed_at' => now()]);
                }
                $bar->advance();

                continue;
            }

            if ($this->option('dry-run')) {
                // $this->line(" [Dry Run] Would discharge Visit #{$visit->id}");
                $bar->advance();

                continue;
            }

            try {
                DB::transaction(function () use ($visit, $booking) {
                    $now = Carbon::now(config('app.timezone', 'Asia/Kuwait'));

                    // A. Release Room
                    $tableId = $booking->table_id ?? $visit->restaurant_table_id;
                    if ($tableId) {
                        RestaurantTable::where('id', $tableId)->update(['status' => 'available']);
                    }

                    // B. Update Booking
                    $meta = (array) ($booking->meta ?? []);
                    $meta['auto_discharged_at'] = $now->toDateTimeString();
                    $meta['auto_discharged_reason'] = 'Cron: Service started but not completed.';

                    $booking->update([
                        'status' => 'completed',
                        'checked_in_at' => null,
                        'meta' => $meta,
                    ]);

                    // C. Update Visit
                    $newNotes = trim(($visit->notes ?? '')."\n[System]: Auto-completed by nightly cleanup.");

                    $visit->update([
                        'status' => 'completed',
                        'completed_at' => $now,
                        'notes' => $newNotes,
                    ]);
                });
            } catch (\Throwable $e) {
                Log::error("Cleanup: Failed to discharge Booking #{$booking->id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Task 2: Cancel bookings that missed their date.
     */
    protected function markStaleBookings()
    {
        $this->info('--- Task 2: Cancelling Zombie Bookings ---');

        // "Zombie" = Scheduled before today, never checked in, still says "Pending" or "Confirmed"
        $yesterday = now()->startOfDay();

        $zombies = Booking::query()
            ->where('res_end', '<', $yesterday)
            ->whereNull('checked_in_at')
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $count = $zombies->count();

        if ($count === 0) {
            $this->info('No zombie bookings found.');

            return;
        }

        $this->info("Found {$count} zombie bookings.");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($zombies as $booking) {
            if ($this->option('dry-run')) {
                // $this->line(" [Dry Run] Would cancel Booking #{$booking->id}");
                $bar->advance();

                continue;
            }

            // Mark as 'cancelled' (safer than deleting)
            // If it was 'confirmed', we flag it as a 'No Show' in meta for reports.
            $meta = (array) $booking->meta;
            $meta['auto_cleanup_reason'] = 'Cron: Past due date without check-in.';

            if ($booking->status === 'confirmed') {
                $meta['is_no_show'] = true;
            }

            $booking->update([
                'status' => 'cancelled',
                'meta' => $meta,
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Task 3: Release tables stuck as 'occupied'.
     */
    protected function releaseGhostTables()
    {
        $this->info('--- Task 3: Releasing Ghost Tables ---');

        // Find all tables that claim to be occupied
        $occupiedTables = RestaurantTable::where('status', 'occupied')->get();

        $releasedCount = 0;

        foreach ($occupiedTables as $table) {
            // Check if there is ANY active visit for this table
            // Active = Status is NOT (completed OR cancelled OR no_show)
            $hasActiveVisit = Visit::query()
                ->where('restaurant_table_id', $table->id)
                ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
                ->exists();

            if (! $hasActiveVisit) {
                // Ghost detected! The table thinks it's busy, but DB says no one is there.
                if ($this->option('dry-run')) {
                    $this->line(" [Dry Run] Would release Table #{$table->id} ({$table->name})");

                    continue;
                }

                $table->update(['status' => 'available']);
                $releasedCount++;
            }
        }

        $this->info("Released {$releasedCount} ghost tables.");
    }
}
