<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        // MySQL ENUM MODIFY — skip on non-MySQL drivers (SQLite test env).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Add 'no_show' to the bookings.status enum so the no-show action
        // can persist a proper terminal state (instead of overloading 'cancelled'
        // and forcing reports to filter by meta.no_show).
        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('pending','confirmed','cancelled','completed','checked_in','no_show')
             NOT NULL DEFAULT 'confirmed'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        // Demote any no_show rows back to cancelled before contracting the enum.
        DB::table('bookings')->where('status', 'no_show')->update(['status' => 'cancelled']);

        DB::statement(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('pending','confirmed','cancelled','completed','checked_in')
             NOT NULL DEFAULT 'confirmed'"
        );
    }
};
