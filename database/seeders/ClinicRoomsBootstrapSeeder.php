<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

/**
 * Makes sure every branch has at least one consultation room, and that no
 * active doctor is left unbookable for want of one.
 *
 * Why this exists: BookingsController::doctorOptions() only offers doctors with
 * a `restaurant_table_id` — a booking reserves a doctor AND a room together, so
 * a roomless doctor is invisible in the New Booking sheet. On a fresh install
 * the rooms table is empty, which silently makes EVERY doctor unbookable.
 *
 * `restaurant_tables` is the consultation-room table; the name is inherited
 * from the restaurant codebase this app was forked from.
 *
 * Conservative on purpose:
 *   - Creates a default room only for a branch that has NONE.
 *   - Assigns a doctor a room only when they have none AND their branch has
 *     exactly one, so a multi-room branch is never guessed at.
 *
 * Idempotent — re-running changes nothing once each branch has a room.
 */
class ClinicRoomsBootstrapSeeder extends Seeder
{
    private const DEFAULT_ROOM_NAME = 'Room 1';

    public function run(): void
    {
        $this->ensureRooms();
        $this->assignDoctors();
    }

    private function ensureRooms(): void
    {
        $created = 0;

        foreach (Branch::query()->get() as $branch) {
            $hasRoom = RestaurantTable::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->exists();

            if ($hasRoom) {
                continue;
            }

            RestaurantTable::create([
                'branch_id' => $branch->id,
                'name' => self::DEFAULT_ROOM_NAME,
                'capacity' => 1,
                'status' => 'available',
            ]);

            $created++;
        }

        $this->command?->info("Created {$created} default room(s).");
    }

    /**
     * A doctor with no room can't be booked. Only auto-assign where the branch
     * has exactly one room — anything else is a real choice for the clinic.
     */
    private function assignDoctors(): void
    {
        $assigned = 0;
        $ambiguous = 0;

        $doctors = Doctor::withoutGlobalScopes()
            ->whereNull('restaurant_table_id')
            ->whereNotNull('branch_id')
            ->get();

        foreach ($doctors as $doctor) {
            $rooms = RestaurantTable::withoutGlobalScopes()
                ->where('branch_id', $doctor->branch_id)
                ->get();

            if ($rooms->count() !== 1) {
                $ambiguous++;

                continue;
            }

            $doctor->forceFill(['restaurant_table_id' => $rooms->first()->id])->save();
            $assigned++;
        }

        $this->command?->info("Assigned {$assigned} doctor(s) to a room.");

        if ($ambiguous > 0) {
            $this->command?->warn("{$ambiguous} doctor(s) left unassigned — their branch has 0 or several rooms; pick one in the Rooms screen.");
        }
    }
}
