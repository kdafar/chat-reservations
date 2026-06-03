<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `deleted_at` to the customer-visible core tables so reception can
 * delete + recover patients, bookings, and doctor records from the trash.
 *
 * Visits/admissions/claims intentionally NOT included — they have their
 * own terminal-state lifecycles (completed/cancelled, discharged, void)
 * and shouldn't double up with a soft-delete column.
 */
return new class extends Migration
{
    private const TABLES = ['patients', 'bookings', 'doctors'];

    public function up(): void
    {
        foreach (self::TABLES as $t) {
            if (! Schema::hasTable($t) || Schema::hasColumn($t, 'deleted_at')) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            if (Schema::hasColumn($t, 'deleted_at')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
