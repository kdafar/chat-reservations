<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore 'draft' and 'hold' to bookings.status enum (audit follow-up review).
     *
     * The original create-bookings migration declared:
     *   ENUM('draft','hold','confirmed','cancelled')
     *
     * `2026_01_04_111532_add_checked_in_to_bookings_status` rewrote it as:
     *   ENUM('pending','confirmed','cancelled','completed','checked_in')
     *
     * — silently dropping 'draft' and 'hold'. But `Booking::S_DRAFT`,
     * `Booking::S_HOLD`, and `config('clinic.follow_up_booking_status') = 'draft'`
     * all still reference them. FollowUpService::createBooking would fail.
     *
     * Restore them plus 'no_show' (from the May follow-up) so the enum
     * matches every value in use anywhere in the codebase.
     */
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        // MySQL ENUM MODIFY — skip on non-MySQL drivers (SQLite test env).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('draft','hold','pending','confirmed','cancelled','completed','checked_in','no_show')
             NOT NULL DEFAULT 'confirmed'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Demote draft/hold rows before contracting the enum.
        DB::table('bookings')->whereIn('status', ['draft', 'hold'])
            ->update(['status' => 'pending']);

        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('pending','confirmed','cancelled','completed','checked_in','no_show')
             NOT NULL DEFAULT 'confirmed'"
        );
    }
};
