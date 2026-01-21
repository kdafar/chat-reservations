<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // We strictly define the new list including OLD values + NEW 'checked_in' value
        \DB::statement("
            ALTER TABLE bookings 
            MODIFY COLUMN status 
            ENUM('pending', 'confirmed', 'cancelled', 'completed', 'checked_in') 
            NOT NULL DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings_status', function (Blueprint $table) {
            //
        });
    }
};
