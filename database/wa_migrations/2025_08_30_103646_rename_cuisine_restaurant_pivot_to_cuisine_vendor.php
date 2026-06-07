<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop FK on the OLD table (points to restaurants/vendors)
        Schema::table('cuisine_restaurant_pivot', function (Blueprint $table) {
            // Less brittle than using the explicit name.
            $table->dropForeign(['restaurant_id']);
        });

        // 2) Rename the table
        Schema::rename('cuisine_restaurant_pivot', 'cuisine_vendor');

        // 3) Rename restaurant_id -> vendor_id (no DBAL, use raw SQL to keep type)
        // Adjust NULL/NOT NULL to match your original column definition; most pivots are NOT NULL.
        DB::statement('ALTER TABLE `'.DB::getTablePrefix().'cuisine_vendor` CHANGE `restaurant_id` `vendor_id` BIGINT UNSIGNED NOT NULL');

        // 4) Recreate FK to the new `vendors` table
        Schema::table('cuisine_vendor', function (Blueprint $table) {
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // 1) Drop FK to vendors
        Schema::table('cuisine_vendor', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
        });

        // 2) Rename vendor_id -> restaurant_id
        DB::statement('ALTER TABLE `'.DB::getTablePrefix().'cuisine_vendor` CHANGE `vendor_id` `restaurant_id` BIGINT UNSIGNED NOT NULL');

        // 3) Rename table back
        Schema::rename('cuisine_vendor', 'cuisine_restaurant_pivot');

        // 4) Restore FK to restaurants
        Schema::table('cuisine_restaurant_pivot', function (Blueprint $table) {
            $table->foreign('restaurant_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');
        });
    }
};
