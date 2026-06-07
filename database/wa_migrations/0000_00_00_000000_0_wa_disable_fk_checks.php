<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Runs first in the module migration set. The source migrations were authored
 * for MySQL with relaxed FK ordering (some FKs reference tables created by a
 * later migration, partly due to a malformed source timestamp). Disabling FK
 * checks for the duration of the migrate run makes the historical order safe.
 * Re-enabled by the matching enable migration at the end of the set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
};
