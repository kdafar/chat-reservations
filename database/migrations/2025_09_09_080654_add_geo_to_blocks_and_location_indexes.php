<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        });

        // Each index added separately, guarded by existence. This lets the
        // migration succeed on a fresh DB (where the previous create_states_*
        // migration already added implicit unique indexes via ->unique()) AND
        // on databases where these explicit indexes are still missing.
        if (! self::indexExists('blocks', 'blocks_city_active_idx')) {
            Schema::table('blocks', fn (Blueprint $t) => $t->index(['city_id', 'is_active'], 'blocks_city_active_idx'));
        }
        if (! self::indexExists('blocks', 'blocks_city_code_unique')) {
            Schema::table('blocks', fn (Blueprint $t) => $t->unique(['city_id', 'code'], 'blocks_city_code_unique'));
        }

        if (! self::indexExists('cities', 'cities_state_active_idx')) {
            Schema::table('cities', fn (Blueprint $t) => $t->index(['state_id', 'is_active'], 'cities_state_active_idx'));
        }
        if (! self::indexExists('cities', 'cities_state_slug_unique')) {
            Schema::table('cities', fn (Blueprint $t) => $t->unique(['state_id', 'slug'], 'cities_state_slug_unique'));
        }

        // The create_states_cities_blocks migration already declared
        // ->unique() on slug, which creates an implicit `states_slug_unique`
        // index on MySQL and `states_slug_unique` (global namespace) on SQLite.
        // Re-adding it under the same name crashes SQLite. Skip if present.
        if (! self::indexExists('states', 'states_slug_unique')) {
            Schema::table('states', fn (Blueprint $t) => $t->unique('slug', 'states_slug_unique'));
        }
    }

    private static function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'sqlite') {
                return collect(DB::select("PRAGMA index_list({$table})"))
                    ->contains(fn ($row) => $row->name === $index);
            }

            // MySQL / MariaDB
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                [$table, $index]
            );

            return ! empty($rows);
        } catch (\Throwable) {
            return false;
        }
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
