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
        Schema::table('blocks', function (Blueprint $table) {
            // Add centroid coordinates (nullable for gradual backfill)
            if (! Schema::hasColumn('blocks', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('blocks', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            // Fast filtering by city & active
            $table->index(['city_id', 'is_active'], 'blocks_city_active_idx');

            // Enforce unique code per city (fails if dupes exist)
            $table->unique(['city_id', 'code'], 'blocks_city_code_unique');
        });

        // --- cities: helpful composite index (optional but useful)
        Schema::table('cities', function (Blueprint $table) {
            $table->index(['state_id', 'is_active'], 'cities_state_active_idx');
            // Slug uniqueness under a state is common in admin URLs
            $table->unique(['state_id', 'slug'], 'cities_state_slug_unique');
        });

        // --- states: ensure slug uniqueness
        Schema::table('states', function (Blueprint $table) {
            $table->unique('slug', 'states_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse in opposite order
        Schema::table('states', function (Blueprint $table) {
            $table->dropUnique('states_slug_unique');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropUnique('cities_state_slug_unique');
            $table->dropIndex('cities_state_active_idx');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropUnique('blocks_city_code_unique');
            $table->dropIndex('blocks_city_active_idx');

            if (Schema::hasColumn('blocks', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('blocks', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
