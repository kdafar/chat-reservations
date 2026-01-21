<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\RestaurantTable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function __construct(protected TableAllocatorService $allocator) {}

    public function checkWindowOrFail(Booking $b): void
    {
        // Allow check-in from 30 min before to 60 min after reservation time
        $date = Carbon::parse($b->res_date);
        $time = Carbon::parse($b->res_time);
        $slot = Carbon::createFromFormat('Y-m-d H:i:s', $date->toDateString().' '.$time->format('H:i:s'), config('app.timezone'));

        $now = now();
        if ($now->lt($slot->copy()->subMinutes(30)) || $now->gt($slot->copy()->addMinutes(60))) {
            throw ValidationException::withMessages([
                'window' => 'Check-in window not active for this booking.',
            ]);
        }
    }

    public function checkInByToken(string $token, ?int $preferredTableId = null): Booking
    {
        return DB::transaction(function () use ($token, $preferredTableId) {
            /** @var Booking $b */
            $b = Booking::where('qr_token', $token)->lockForUpdate()->firstOrFail();

            if ($b->status !== 'confirmed') {
                throw ValidationException::withMessages(['status' => 'Booking is not confirmed.']);
            }
            if ($b->checked_in_at) {
                return $b; // idempotent
            }

            $this->checkWindowOrFail($b);

            // Table selection
            $table = null;
            if ($preferredTableId) {
                $table = RestaurantTable::whereKey($preferredTableId)
                    ->where('branch_id', $b->branch_id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $table) {
                $table = $this->allocator->allocate($b->branch_id, $b->party_size);
            }

            if (! $table) {
                throw ValidationException::withMessages(['table' => 'No suitable table available.']);
            }

            // Occupy
            $table->update(['status' => 'occupied']);

            $b->table_id = $table->id;
            $b->checked_in_at = now();
            $b->status = 'seated'; // or keep 'confirmed' + add a status_label accessor
            $b->save();

            return $b;
        });
    }
}
